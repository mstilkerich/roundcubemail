<?php

/*
 +-----------------------------------------------------------------------+
 | This file is part of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) The Roundcube Dev Team                                  |
 | Copyright (C) Kolab Systems AG                                        |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 | PURPOSE:                                                              |
 |   IMAP Storage Engine                                                 |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 | Author: Aleksander Machniak <alec@alec.pl>                            |
 +-----------------------------------------------------------------------+
*/

/**
 * Interface class for accessing an IMAP server
 */
class rcube_imap extends rcube_storage
{
    /**
     * Instance of rcube_imap_generic
     *
     * @var rcube_imap_generic
     */
    public $conn;

    /** @var ?rcube_imap_cache IMAP messages cache */
    protected $mcache;

    /** @var ?rcube_cache IMAP cache */
    protected $cache;

    protected $plugins;
    protected $delimiter;
    protected $namespace;
    protected $struct_charset;
    protected $search_string = '';
    protected $search_charset = '';
    protected $search_sort_field = '';
    protected $search_threads = false;
    protected $search_sorted = false;
    protected $sort_field = '';
    protected $sort_order = 'DESC';
    protected $caching = false;
    protected $messages_caching = false;
    protected $threading = false;
    protected $connect_done = false;
    protected $list_excludes = [];
    protected $list_root;
    protected $msg_uid;
    protected $sort_folder_collator;

    /**
     * Object constructor.
     */
    public function __construct()
    {
        $this->conn = new rcube_imap_generic();
        $this->plugins = rcube::get_instance()->plugins;

        // Set namespace and delimiter from session,
        // so some methods would work before connection
        if (isset($_SESSION['imap_namespace'])) {
            $this->namespace = $_SESSION['imap_namespace'];
        }
        if (isset($_SESSION['imap_delimiter'])) {
            $this->delimiter = $_SESSION['imap_delimiter'];
        }
        if (!empty($_SESSION['imap_list_conf'])) {
            [$this->list_root, $this->list_excludes] = $_SESSION['imap_list_conf'];
        }
    }

    /**
     * Magic getter for backward compat.
     *
     * @deprecated
     */
    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
    }

    /**
     * Connect to an IMAP server
     *
     * @param string $host    Host to connect
     * @param string $user    Username for IMAP account
     * @param string $pass    Password for IMAP account
     * @param int    $port    Port to connect to
     * @param string $use_ssl SSL schema (either ssl or tls) or null if plain connection
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function connect($host, $user, $pass, $port = 143, $use_ssl = null)
    {
        // check for OpenSSL support in PHP build
        if ($use_ssl && extension_loaded('openssl')) {
            $this->options['ssl_mode'] = $use_ssl == 'imaps' ? 'ssl' : $use_ssl;
        } elseif ($use_ssl) {
            rcube::raise_error([
                'code' => 403,
                'type' => 'imap',
                'message' => 'OpenSSL not available',
            ], true, false);

            $port = 143;
        }

        $this->options['port'] = $port;

        if (!empty($this->options['debug'])) {
            $this->set_debug(true);

            $this->options['ident'] = [
                'name' => 'Roundcube',
                'version' => RCUBE_VERSION,
                'php' => \PHP_VERSION,
                'os' => \PHP_OS,
                'command' => $_SERVER['REQUEST_URI'] ?? '',
            ];
        }

        $attempt = 0;
        do {
            $data = [
                'host' => $host,
                'user' => $user,
                'attempt' => ++$attempt,
                'retry' => false,
            ];

            $data = $this->plugins->exec_hook('storage_connect', array_merge($this->options, $data));

            if ($attempt > 1 && !$data['retry']) {
                break;
            }

            if (!empty($data['pass'])) {
                $pass = $data['pass'];
            }

            // Handle per-host socket options
            if (isset($data['socket_options'])) {
                rcube_utils::parse_socket_options($data['socket_options'], $data['host']);
            }

            $this->conn->connect($data['host'], $data['user'], $pass, $data);
        } while (!$this->conn->connected() && $data['retry']);

        $config = [
            'host' => $data['host'],
            'user' => $data['user'],
            'password' => $pass,
            'port' => $port,
            'ssl' => $use_ssl,
        ];

        $this->options = array_merge($this->options, $config);
        $this->connect_done = true;

        if ($this->conn->connected()) {
            // check for session identifier
            $session = null;
            if (preg_match('/\s+SESSIONID=([^=\s]+)/', $this->conn->result, $m)) {
                $session = $m[1];
            }

            // get namespace and delimiter
            $this->set_env();

            // trigger post-connect hook
            $this->plugins->exec_hook('storage_connected', [
                'host' => $host, 'user' => $user, 'session' => $session,
            ]);

            return true;
        }
        // write error log
        elseif ($this->conn->error) {
            // When log_logins=true the entry in userlogins.log will be created
            // in this case another error message is redundant, skip it
            if ($pass && $user && !rcube::get_instance()->config->get('log_logins')) {
                $message = sprintf('Login failed for %s against %s from %s. %s',
                    $user, $host, rcube_utils::remote_ip(), $this->conn->error);

                rcube::raise_error([
                    'code' => 403,
                    'type' => 'imap',
                    'message' => $message,
                ], true, false);
            }
        }

        return false;
    }

    /**
     * Close IMAP connection.
     * Usually done on script shutdown
     */
    #[Override]
    public function close()
    {
        $this->connect_done = false;
        $this->conn->closeConnection();

        if ($this->mcache) {
            $this->mcache->close();
        }
    }

    /**
     * Check connection state, connect if not connected.
     *
     * @return bool connection state
     */
    #[Override]
    public function check_connection()
    {
        // Establish connection if it wasn't done yet
        if (!$this->connect_done && !empty($this->options['user'])) {
            return $this->connect(
                $this->options['host'],
                $this->options['user'],
                $this->options['password'],
                $this->options['port'],
                $this->options['ssl']
            );
        }

        return $this->is_connected();
    }

    /**
     * Checks IMAP connection.
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function is_connected()
    {
        return $this->conn->connected();
    }

    /**
     * Returns code of last error
     *
     * @return int Error code
     */
    #[Override]
    public function get_error_code()
    {
        return $this->conn->errornum;
    }

    /**
     * Returns text of last error
     *
     * @return string Error string
     */
    #[Override]
    public function get_error_str()
    {
        return $this->conn->error;
    }

    /**
     * Returns code of last command response
     *
     * @return int Response code
     */
    #[Override]
    public function get_response_code()
    {
        switch ($this->conn->resultcode) {
            case 'NOPERM':
                return self::NOPERM;
            case 'READ-ONLY':
                return self::READONLY;
            case 'TRYCREATE':
                return self::TRYCREATE;
            case 'INUSE':
                return self::INUSE;
            case 'OVERQUOTA':
                return self::OVERQUOTA;
            case 'ALREADYEXISTS':
                return self::ALREADYEXISTS;
            case 'NONEXISTENT':
                return self::NONEXISTENT;
            case 'CONTACTADMIN':
                return self::CONTACTADMIN;
            default:
                return self::UNKNOWN;
        }
    }

    /**
     * Activate/deactivate debug mode
     *
     * @param bool $dbg True if IMAP conversation should be logged
     */
    #[Override]
    public function set_debug($dbg = true)
    {
        $this->options['debug'] = $dbg;
        $this->conn->setDebug($dbg, [$this, 'debug_handler']);
    }

    /**
     * Set internal folder reference.
     * All operations will be performed on this folder.
     *
     * @param string $folder Folder name
     */
    #[Override]
    public function set_folder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * Save a search result for future message listing methods
     *
     * @param array $set Search set, result from rcube_imap::get_search_set():
     *                   0 - searching criteria, string
     *                   1 - search result, rcube_result_index|rcube_result_thread
     *                   2 - searching character set, string
     *                   3 - sorting field, string
     *                   4 - true if sorted, bool
     */
    #[Override]
    public function set_search_set($set)
    {
        $set = (array) $set;

        $this->search_string = $set[0] ?? null;
        $this->search_set = $set[1] ?? null;
        $this->search_charset = $set[2] ?? null;
        $this->search_sort_field = $set[3] ?? null;
        $this->search_sorted = $set[4] ?? null;
        $this->search_threads = is_a($this->search_set, 'rcube_result_thread');

        if (is_a($this->search_set, 'rcube_result_multifolder')) {
            $this->set_threading(false);
        }
    }

    /**
     * Return the saved search set as hash array
     *
     * @return array|null Search set
     */
    #[Override]
    public function get_search_set()
    {
        if (empty($this->search_set)) {
            return null;
        }

        return [
            $this->search_string,
            $this->search_set,
            $this->search_charset,
            $this->search_sort_field,
            $this->search_sorted,
        ];
    }

    /**
     * Returns the IMAP server's capability.
     *
     * @param string $cap Capability name
     *
     * @return mixed Capability value or TRUE if supported, FALSE if not
     */
    #[Override]
    public function get_capability($cap)
    {
        $cap = strtoupper($cap);
        $sess_key = "STORAGE_{$cap}";

        if (!isset($_SESSION[$sess_key])) {
            if (!$this->check_connection()) {
                return false;
            }

            if ($cap == rcube_storage::DUAL_USE_FOLDERS) {
                $_SESSION[$sess_key] = $this->detect_dual_use_folders();
            } else {
                $_SESSION[$sess_key] = $this->conn->getCapability($cap);
            }
        }

        return $_SESSION[$sess_key];
    }

    /**
     * Checks the PERMANENTFLAGS capability of the current folder
     * and returns true if the given flag is supported by the IMAP server
     *
     * @param string $flag Permanentflag name
     *
     * @return bool True if this flag is supported
     */
    #[Override]
    public function check_permflag($flag)
    {
        $flag = strtoupper($flag);
        $perm_flags = $this->get_permflags($this->folder);
        $imap_flag = $this->conn->flags[$flag];

        return $imap_flag && !empty($perm_flags) && in_array_nocase($imap_flag, $perm_flags);
    }

    /**
     * Returns PERMANENTFLAGS of the specified folder
     *
     * @param string $folder Folder name
     *
     * @return array Flags
     */
    public function get_permflags($folder)
    {
        if (!strlen($folder)) {
            return [];
        }

        if (!$this->check_connection()) {
            return [];
        }

        if ($this->conn->select($folder)) {
            $permflags = $this->conn->data['PERMANENTFLAGS'];
        } else {
            return [];
        }

        if (!isset($permflags) || !is_array($permflags)) {
            $permflags = [];
        }

        return $permflags;
    }

    /**
     * Returns the delimiter that is used by the IMAP server for folder separation
     *
     * @return string Delimiter string
     */
    #[Override]
    public function get_hierarchy_delimiter()
    {
        return $this->delimiter;
    }

    /**
     * Get namespace
     *
     * @param string $name Namespace array index: personal, other, shared, prefix
     *
     * @return string|array|null Namespace data
     */
    #[Override]
    public function get_namespace($name = null)
    {
        $ns = $this->namespace;

        if ($name) {
            // an alias for BC
            if ($name == 'prefix') {
                $name = 'prefix_in';
            }

            return $ns[$name] ?? null;
        }

        unset($ns['prefix_in'], $ns['prefix_out']);

        return $ns;
    }

    /**
     * Sets delimiter and namespaces
     */
    protected function set_env()
    {
        if ($this->delimiter !== null && $this->namespace !== null) {
            return;
        }

        $config = rcube::get_instance()->config;
        $imap_personal = $config->get('imap_ns_personal');
        $imap_other = $config->get('imap_ns_other');
        $imap_shared = $config->get('imap_ns_shared');
        $imap_delimiter = $config->get('imap_delimiter');

        if (!$this->check_connection()) {
            return;
        }

        $ns = $this->conn->getNamespace();

        // Set namespaces (NAMESPACE supported)
        if (is_array($ns)) {
            $this->namespace = $ns;
        } else {
            $this->namespace = [
                'personal' => null,
                'other' => null,
                'shared' => null,
            ];
        }

        if ($imap_delimiter) {
            $this->delimiter = $imap_delimiter;
        }
        if (empty($this->delimiter) && !empty($this->namespace['personal'][0][1])) {
            $this->delimiter = $this->namespace['personal'][0][1];
        }
        if (empty($this->delimiter)) {
            $this->delimiter = $this->conn->getHierarchyDelimiter();
        }
        if (empty($this->delimiter)) {
            $this->delimiter = '/';
        }

        $this->list_root = null;
        $this->list_excludes = [];

        // Overwrite namespaces
        if ($imap_personal !== null) {
            $this->namespace['personal'] = null;
            foreach ((array) $imap_personal as $dir) {
                $this->namespace['personal'][] = [$dir, $this->delimiter];
            }
        }

        if ($imap_other === false) {
            foreach ((array) $this->namespace['other'] as $dir) {
                if (is_array($dir) && !empty($dir[0])) {
                    $this->list_excludes[] = $dir[0];
                }
            }

            $this->namespace['other'] = null;
        } elseif ($imap_other !== null) {
            $this->namespace['other'] = null;
            foreach ((array) $imap_other as $dir) {
                if ($dir) {
                    $this->namespace['other'][] = [$dir, $this->delimiter];
                }
            }
        }

        if ($imap_shared === false) {
            foreach ((array) $this->namespace['shared'] as $dir) {
                if (is_array($dir) && !empty($dir[0])) {
                    $this->list_excludes[] = $dir[0];
                }
            }

            $this->namespace['shared'] = null;
        } elseif ($imap_shared !== null) {
            $this->namespace['shared'] = null;
            foreach ((array) $imap_shared as $dir) {
                if ($dir) {
                    $this->namespace['shared'][] = [$dir, $this->delimiter];
                }
            }
        }

        // Performance optimization for case where we have no shared/other namespace
        // and personal namespace has one prefix (#5073)
        // In such a case we can tell the server to return only content of the
        // specified folder in LIST/LSUB, no post-filtering
        if (empty($this->namespace['other']) && empty($this->namespace['shared'])
            && !empty($this->namespace['personal']) && count($this->namespace['personal']) === 1
            && strlen($this->namespace['personal'][0][0]) > 1
        ) {
            $this->list_root = $this->namespace['personal'][0][0];
            $this->list_excludes = [];
        }

        // Find personal namespace prefix(es) for self::mod_folder()
        if (!empty($this->namespace['personal']) && is_array($this->namespace['personal'])) {
            // There can be more than one namespace root,
            // - for prefix_out get the first one but only
            //   if there is only one root
            // - for prefix_in get the first one but only
            //   if there is no non-prefixed namespace root (#5403)
            $roots = [];
            foreach ($this->namespace['personal'] as $namespace) {
                $roots[] = $namespace[0];
            }

            if (!in_array('', $roots)) {
                $this->namespace['prefix_in'] = $roots[0];
            }
            if (count($roots) == 1) {
                $this->namespace['prefix_out'] = $roots[0];
            }
        }

        $_SESSION['imap_namespace'] = $this->namespace;
        $_SESSION['imap_delimiter'] = $this->delimiter;
        $_SESSION['imap_list_conf'] = [$this->list_root, $this->list_excludes];
    }

    /**
     * Returns IMAP server vendor name
     *
     * @return string|null Vendor name
     *
     * @since 1.2
     */
    #[Override]
    public function get_vendor()
    {
        if (isset($_SESSION['imap_vendor'])) {
            return $_SESSION['imap_vendor'];
        }

        $config = rcube::get_instance()->config;
        $imap_vendor = $config->get('imap_vendor');

        if ($imap_vendor) {
            return $imap_vendor;
        }

        if (!$this->check_connection()) {
            return null;
        }

        if (isset($this->conn->data['ID'])) {
            $ident = $this->conn->data['ID'];
        } elseif ($this->get_capability('ID')) {
            $ident = $this->conn->id([
                'name' => 'Roundcube',
                'version' => RCUBE_VERSION,
                'php' => \PHP_VERSION,
                'os' => \PHP_OS,
            ]);
        } else {
            $ident = null;
        }

        $vendor = (string) ($ident['name'] ?? $ident['NAME'] ?? '');
        $ident = strtolower($vendor . ' ' . $this->conn->data['GREETING']);
        $vendors = ['cyrus', 'dovecot', 'uw-imap', 'gimap', 'hmail', 'greenmail'];

        foreach ($vendors as $v) {
            if (strpos($ident, $v) !== false) {
                $vendor = $v;
                break;
            }
        }

        return $_SESSION['imap_vendor'] = $vendor;
    }

    /**
     * Get message count for a specific folder
     *
     * @param ?string $folder Folder name
     * @param string  $mode   Mode for count [ALL|THREADS|UNSEEN|RECENT|EXISTS]
     * @param bool    $force  Force reading from server and update cache
     * @param bool    $status Enables storing folder status info (max UID/count),
     *                        required for folder_status()
     *
     * @return int Number of messages
     */
    #[Override]
    public function count($folder = null, $mode = 'ALL', $force = false, $status = true)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        return $this->countmessages($folder, $mode, $force, $status);
    }

    /**
     * Protected method for getting number of messages
     *
     * @param string $folder    Folder name
     * @param string $mode      Mode for count [ALL|THREADS|UNSEEN|RECENT|EXISTS]
     * @param bool   $force     Force reading from server and update cache
     * @param bool   $status    Enables storing folder status info (max UID/count),
     *                          required for folder_status()
     * @param bool   $no_search Ignore current search result
     *
     * @return int Number of messages
     *
     * @see rcube_imap::count()
     */
    protected function countmessages($folder, $mode = 'ALL', $force = false, $status = true, $no_search = false)
    {
        $mode = strtoupper($mode);

        // Count search set, assume search set is always up-to-date (don't check $force flag)
        // @TODO: this could be handled in more reliable way, e.g. a separate method
        //        maybe in rcube_imap_search
        if (!$no_search && $this->search_string && $folder == $this->folder) {
            if ($mode == 'ALL') {
                return $this->search_set->count_messages();
            }
            if ($mode == 'THREADS') {
                return $this->search_set->count();
            }
        }

        // EXISTS is a special alias for ALL, it allows to get the number
        // of all messages in a folder also when search is active and with
        // any skip_deleted setting

        $a_folder_cache = $this->get_cache('messagecount');

        // return cached value
        if (!$force && isset($a_folder_cache[$folder][$mode])) {
            return $a_folder_cache[$folder][$mode];
        }

        if (!isset($a_folder_cache[$folder]) || !is_array($a_folder_cache[$folder])) {
            $a_folder_cache[$folder] = [];
        }

        if ($mode == 'THREADS') {
            $res = $this->threads($folder);
            $count = $res->count();

            if ($status) {
                $msg_count = $res->count_messages();
                $this->set_folder_stats($folder, 'cnt', $msg_count);
                $this->set_folder_stats($folder, 'maxuid', $msg_count ? $this->id2uid($msg_count, $folder) : 0);
            }
        }
        // Need connection here
        elseif (!$this->check_connection()) {
            return 0;
        }
        // RECENT count is fetched a bit different
        elseif ($mode == 'RECENT') {
            $count = $this->conn->countRecent($folder);
        }
        // use SEARCH for message counting
        elseif ($mode != 'EXISTS' && !empty($this->options['skip_deleted'])) {
            $search_str = 'ALL UNDELETED';
            $keys = ['COUNT'];

            if ($mode == 'UNSEEN') {
                $search_str .= ' UNSEEN';
            } else {
                if ($this->messages_caching) {
                    $keys[] = 'ALL';
                }
                if ($status) {
                    $keys[] = 'MAX';
                }
            }

            // @TODO: if $mode == 'ALL' we could try to use cache index here

            // get message count using (E)SEARCH
            // not very performant but more precise (using UNDELETED)
            $index = $this->conn->search($folder, $search_str, true, $keys);
            $count = $index->count();

            if ($mode == 'ALL') {
                // Cache index data, will be used in index_direct()
                $this->icache['undeleted_idx'] = $index;

                if ($status) {
                    $this->set_folder_stats($folder, 'cnt', $count);
                    $this->set_folder_stats($folder, 'maxuid', $index->max());
                }
            }
        } else {
            if ($mode == 'UNSEEN') {
                $count = $this->conn->countUnseen($folder);
            } else {
                $count = $this->conn->countMessages($folder);
                if ($status && $mode == 'ALL') {
                    $this->set_folder_stats($folder, 'cnt', $count);
                    $this->set_folder_stats($folder, 'maxuid', $count ? $this->id2uid($count, $folder) : 0);
                }
            }
        }

        $count = (int) $count;

        if (!isset($a_folder_cache[$folder][$mode]) || $a_folder_cache[$folder][$mode] !== $count) {
            $a_folder_cache[$folder][$mode] = $count;

            // write back to cache
            $this->update_cache('messagecount', $a_folder_cache);
        }

        return $count;
    }

    /**
     * Public method for listing message flags
     *
     * @param ?string $folder  Folder name
     * @param array   $uids    Message UIDs
     * @param int     $mod_seq Optional MODSEQ value (of last flag update)
     *
     * @return array Indexed array with message flags
     */
    #[Override]
    public function list_flags($folder, $uids, $mod_seq = null)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        if (!$this->check_connection()) {
            return [];
        }

        // @TODO: when cache was synchronized in this request
        // we might already have asked for flag updates, use it.

        $flags = $this->conn->fetch($folder, $uids, true, ['FLAGS'], $mod_seq);
        $result = [];

        if (!empty($flags)) {
            foreach ($flags as $message) {
                $result[$message->uid] = $message->flags;
            }
        }

        return $result;
    }

    /**
     * Public method for listing headers
     *
     * @param ?string $folder     Folder name
     * @param int     $page       Current page to list
     * @param string  $sort_field Header field to sort by
     * @param string  $sort_order Sort order [ASC|DESC]
     * @param int     $slice      Number of slice items to extract from result array
     *
     * @return array<rcube_message_header> Indexed array with message header objects
     */
    #[Override]
    public function list_messages($folder = null, $page = null, $sort_field = null, $sort_order = null, $slice = 0)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        return $this->_list_messages($folder, $page, $sort_field, $sort_order, $slice);
    }

    /**
     * protected method for listing message headers
     *
     * @param string $folder     Folder name
     * @param int    $page       Current page to list
     * @param string $sort_field Header field to sort by
     * @param string $sort_order Sort order [ASC|DESC]
     * @param int    $slice      Number of slice items to extract from result array
     *
     * @return array Indexed array with message header objects
     *
     * @see rcube_imap::list_messages
     */
    protected function _list_messages($folder, $page = null, $sort_field = null, $sort_order = null, $slice = 0)
    {
        $this->set_sort_order($sort_field, $sort_order);
        $page = $page ?: $this->list_page;

        // use saved message set
        if ($this->search_string) {
            return $this->list_search_messages($folder, $page, $slice);
        }

        if ($this->threading) {
            return $this->list_thread_messages($folder, $page, $slice);
        }

        // get UIDs of all messages in the folder, sorted
        $index = $this->index($folder, $this->sort_field, $this->sort_order);

        if ($index->is_empty()) {
            return [];
        }

        $from = ($page - 1) * $this->page_size;
        $to = $from + $this->page_size;

        $index->slice($from, $to - $from);

        if ($slice) {
            $index->slice(-$slice, $slice);
        }

        // fetch requested messages headers
        $a_index = $index->get();
        $a_msg_headers = $this->fetch_headers($folder, $a_index);

        return array_values($a_msg_headers);
    }

    /**
     * protected method for listing message headers using threads
     *
     * @param string $folder Folder name
     * @param int    $page   Current page to list
     * @param int    $slice  Number of slice items to extract from result array
     *
     * @return array Indexed array with message header objects
     *
     * @see rcube_imap::list_messages
     */
    protected function list_thread_messages($folder, $page, $slice = 0)
    {
        // get all threads (not sorted)
        $threads = $this->threads($folder);

        return $this->fetch_thread_headers($folder, $threads, $page, $slice);
    }

    /**
     * Method for fetching threads data
     *
     * @param string $folder Folder name
     *
     * @return rcube_result_thread Thread data object
     */
    public function threads($folder)
    {
        if ($mcache = $this->get_mcache_engine()) {
            // don't store in self's internal cache, cache has it's own internal cache
            return $mcache->get_thread($folder);
        }

        if (!empty($this->icache['threads'])) {
            if ($this->icache['threads']->get_parameters('MAILBOX') == $folder) {
                return $this->icache['threads'];
            }
        }

        // get all threads
        $result = $this->threads_direct($folder);

        // add to internal (fast) cache
        return $this->icache['threads'] = $result;
    }

    /**
     * Method for direct fetching of threads data
     *
     * @param string $folder Folder name
     *
     * @return rcube_result_thread Thread data object
     */
    public function threads_direct($folder)
    {
        if (!$this->check_connection()) {
            return new rcube_result_thread();
        }

        // get all threads
        return $this->conn->thread($folder, $this->threading,
            $this->options['skip_deleted'] ? 'UNDELETED' : '', true);
    }

    /**
     * protected method for fetching threaded messages headers
     *
     * @param string              $folder  Folder name
     * @param rcube_result_thread $threads Threads data object
     * @param int                 $page    List page number
     * @param int                 $slice   Number of threads to slice
     *
     * @return array Messages headers
     */
    protected function fetch_thread_headers($folder, $threads, $page, $slice = 0)
    {
        // Sort thread structure
        $this->sort_threads($threads);

        $from = ($page - 1) * $this->page_size;
        $to = $from + $this->page_size;

        $threads->slice($from, $to - $from);

        if ($slice) {
            $threads->slice(-$slice, $slice);
        }

        // Get UIDs of all messages in all threads
        $a_index = $threads->get();

        // fetch requested headers from server
        $a_msg_headers = $this->fetch_headers($folder, $a_index);

        unset($a_index);

        // Set depth, has_children and unread_children fields in headers
        $this->set_thread_flags($a_msg_headers, $threads);

        return array_values($a_msg_headers);
    }

    /**
     * Protected method for setting threaded messages flags:
     * depth, has_children, unread_children, flagged_children
     *
     * @param array               $headers Reference to headers array indexed by message UID
     * @param rcube_result_thread $threads Threads data object
     */
    protected function set_thread_flags(&$headers, $threads)
    {
        $parents = [];

        [$msg_depth, $msg_children] = $threads->get_thread_data();

        foreach ($headers as $uid => $header) {
            $depth = $msg_depth[$uid] ?? 0;
            $parents = array_slice($parents, 0, $depth);

            if (!empty($parents)) {
                $headers[$uid]->parent_uid = end($parents);
                if (empty($header->flags['SEEN'])) {
                    $headers[$parents[0]]->unread_children++;
                }
                if (!empty($header->flags['FLAGGED'])) {
                    $headers[$parents[0]]->flagged_children++;
                }
            }

            $parents[] = $uid;

            $headers[$uid]->depth = $depth;
            $headers[$uid]->has_children = !empty($msg_children[$uid]);
            $headers[$uid]->unread_children = 0;
            $headers[$uid]->flagged_children = 0;
        }
    }

    /**
     * A protected method for listing a set of message headers (search results)
     *
     * @param string $folder Folder name
     * @param int    $page   Current page to list
     * @param int    $slice  Number of slice items to extract from the result array
     *
     * @return array Indexed array with message header objects
     */
    protected function list_search_messages($folder, $page, $slice = 0)
    {
        if (!strlen($folder) || empty($this->search_set) || $this->search_set->is_empty()) {
            return [];
        }

        $from = ($page - 1) * $this->page_size;

        // gather messages from a multi-folder search
        if (!empty($this->search_set->multi)) {
            $page_size = $this->page_size;
            $sort_field = $this->sort_field;
            $search_set = $this->search_set;

            // fetch resultset headers, sort and slice them
            if (!empty($sort_field) && $search_set->get_parameters('SORT') != $sort_field) {
                $this->sort_field = null;
                $this->page_size = 1000;  // fetch up to 1000 matching messages per folder
                $this->threading = false;

                $a_msg_headers = [];
                foreach ($search_set->sets as $resultset) {
                    if (!$resultset->is_empty()) {
                        $this->search_set = $resultset;
                        $this->search_threads = $resultset instanceof rcube_result_thread;

                        $a_headers = $this->list_search_messages($resultset->get_parameters('MAILBOX'), 1);
                        $a_msg_headers = array_merge($a_msg_headers, $a_headers);
                        unset($a_headers);
                    }
                }

                // sort headers
                if (!empty($a_msg_headers)) {
                    $a_msg_headers = rcube_imap_generic::sortHeaders($a_msg_headers, $sort_field, $this->sort_order);
                }

                // store (sorted) message index
                $search_set->set_message_index($a_msg_headers, $sort_field, $this->sort_order);

                // only return the requested part of the set
                $a_msg_headers = array_slice(array_values($a_msg_headers), $from, $page_size);
            } else {
                if ($this->sort_order != $search_set->get_parameters('ORDER')) {
                    $search_set->revert();
                }

                // slice resultset first...
                $index = array_slice($search_set->get(), $from, $page_size);
                $fetch = [];

                foreach ($index as $msg_id) {
                    [$uid, $folder] = explode('-', $msg_id, 2);
                    $fetch[$folder][] = $uid;
                }

                // ... and fetch the requested set of headers
                $a_msg_headers = [];
                foreach ($fetch as $folder_name => $a_index) {
                    $a_msg_headers = array_merge($a_msg_headers, array_values($this->fetch_headers($folder_name, $a_index)));
                }

                // Re-sort the result according to the original search set order
                usort($a_msg_headers, static function ($a, $b) use ($index) {
                    return array_search($a->uid . '-' . $a->folder, $index) - array_search($b->uid . '-' . $b->folder, $index);
                });
            }

            if ($slice) {
                $a_msg_headers = array_slice($a_msg_headers, -$slice, $slice);
            }

            // restore members
            $this->sort_field = $sort_field;
            $this->page_size = $page_size;
            $this->search_set = $search_set;

            return $a_msg_headers;
        }

        // use saved messages from searching
        if ($this->threading) {
            return $this->list_search_thread_messages($folder, $page, $slice);
        }

        // search set is threaded, we need a new one
        if ($this->search_threads) {
            $this->search('', $this->search_string, $this->search_charset, $this->sort_field);
        }

        $index = clone $this->search_set;

        // return empty array if no messages found
        if ($index->is_empty()) {
            return [];
        }

        // quickest method (default sorting)
        if (!$this->search_sort_field && !$this->sort_field) {
            $got_index = true;
        }
        // sorted messages, so we can first slice array and then fetch only wanted headers
        elseif ($this->search_sorted) { // SORT searching result
            $got_index = true;
            // reset search set if sorting field has been changed
            if ($this->sort_field && $this->search_sort_field != $this->sort_field) {
                $this->search('', $this->search_string, $this->search_charset, $this->sort_field);

                $index = clone $this->search_set;

                // return empty array if no messages found
                if ($index->is_empty()) {
                    return [];
                }
            }
        }

        if (!empty($got_index)) {
            if ($this->sort_order != $index->get_parameters('ORDER')) {
                $index->revert();
            }

            // get messages uids for one page
            $index->slice($from, $this->page_size);

            if ($slice) {
                $index->slice(-$slice, $slice);
            }

            // fetch headers
            $a_index = $index->get();
            $a_msg_headers = $this->fetch_headers($folder, $a_index);

            return array_values($a_msg_headers);
        }

        // SEARCH result, need sorting
        $cnt = $index->count();

        // 300: experimental value for best result
        if (($cnt > 300 && $cnt > $this->page_size) || !$this->sort_field) {
            // use memory less expensive (and quick) method for big result set
            $index = clone $this->index('', $this->sort_field, $this->sort_order);
            // get messages uids for one page...
            $index->slice($from, $this->page_size);

            if ($slice) {
                $index->slice(-$slice, $slice);
            }

            // ...and fetch headers
            $a_index = $index->get();
            $a_msg_headers = $this->fetch_headers($folder, $a_index);

            return array_values($a_msg_headers);
        }

        // for small result set we can fetch all messages headers
        $a_index = $index->get();
        $a_msg_headers = $this->fetch_headers($folder, $a_index, false);

        // return empty array if no messages found
        if (empty($a_msg_headers)) {
            return [];
        }

        // if not already sorted
        $a_msg_headers = rcube_imap_generic::sortHeaders(
            $a_msg_headers, $this->sort_field, $this->sort_order);

        $a_msg_headers = array_slice(array_values($a_msg_headers), $from, $this->page_size);

        if ($slice) {
            $a_msg_headers = array_slice($a_msg_headers, -$slice, $slice);
        }

        return $a_msg_headers;
    }

    /**
     * protected method for listing a set of threaded message headers (search results)
     *
     * @param string $folder Folder name
     * @param int    $page   Current page to list
     * @param int    $slice  Number of slice items to extract from result array
     *
     * @return array Indexed array with message header objects
     *
     * @see rcube_imap::list_search_messages()
     */
    protected function list_search_thread_messages($folder, $page, $slice = 0)
    {
        // update search_set if previous data was fetched with disabled threading
        if (!$this->search_threads) {
            if ($this->search_set->is_empty()) {
                return [];
            }
            $this->search('', $this->search_string, $this->search_charset, $this->sort_field);
        }

        return $this->fetch_thread_headers($folder, clone $this->search_set, $page, $slice);
    }

    /**
     * Fetches messages headers (by UID)
     *
     * @param string $folder Folder name
     * @param array  $msgs   Message UIDs
     * @param bool   $sort   Enables result sorting by $msgs
     * @param bool   $force  Disables cache use
     *
     * @return array Messages headers indexed by UID
     */
    #[Override]
    public function fetch_headers($folder, $msgs, $sort = true, $force = false)
    {
        if (empty($msgs)) {
            return [];
        }

        if (!$force && ($mcache = $this->get_mcache_engine())) {
            $headers = $mcache->get_messages($folder, $msgs);
        } elseif (!$this->check_connection()) {
            return [];
        } else {
            // fetch requested headers from server
            $headers = $this->conn->fetchHeaders(
                $folder, $msgs, true, false, $this->get_fetch_headers(), $this->get_fetch_items());
        }

        if (empty($headers)) {
            return [];
        }

        $msg_headers = [];
        foreach ($headers as $h) {
            $h->folder = $folder;
            $msg_headers[$h->uid] = $h;
        }

        if ($sort) {
            // use this class for message sorting
            $sorter = new rcube_message_header_sorter();
            $sorter->set_index($msgs);
            $sorter->sort_headers($msg_headers);
        }

        return $msg_headers;
    }

    /**
     * Returns current status of a folder (compared to the last time use)
     *
     * We compare the maximum UID to determine the number of
     * new messages because the RECENT flag is not reliable.
     *
     * @param string $folder Folder name
     * @param array  $diff   Difference data
     *
     * @return int Folder status
     */
    #[Override]
    public function folder_status($folder = null, &$diff = [])
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        $old = $this->get_folder_stats($folder);

        // refresh message count -> will update
        $this->countmessages($folder, 'ALL', true, true, true);

        $result = 0;

        if (empty($old)) {
            return $result;
        }

        $new = $this->get_folder_stats($folder);

        // got new messages
        if ($new['maxuid'] > $old['maxuid']) {
            $result++;
            // get new message UIDs range, that can be used for example
            // to get the data of these messages
            $diff['new'] = ($old['maxuid'] + 1 < $new['maxuid'] ? ($old['maxuid'] + 1) . ':' : '') . $new['maxuid'];
        }

        // some messages has been deleted
        if ($new['cnt'] < $old['cnt']) {
            $result += 2;
        }

        // @TODO: optional checking for messages flags changes (?)
        // @TODO: UIDVALIDITY checking

        return $result;
    }

    /**
     * Stores folder statistic data in session
     *
     * @TODO: move to separate DB table (cache?)
     *
     * @param string $folder Folder name
     * @param string $name   Data name
     * @param mixed  $data   Data value
     */
    protected function set_folder_stats($folder, $name, $data)
    {
        $_SESSION['folders'][$folder][$name] = $data;
    }

    /**
     * Gets folder statistic data
     *
     * @param string $folder Folder name
     *
     * @return array Stats data
     */
    protected function get_folder_stats($folder)
    {
        if (isset($_SESSION['folders'][$folder])) {
            return (array) $_SESSION['folders'][$folder];
        }

        return [];
    }

    /**
     * Return sorted list of message UIDs
     *
     * @param ?string $folder     Folder to get index from
     * @param string  $sort_field Sort column
     * @param string  $sort_order Sort order [ASC, DESC]
     * @param bool    $no_threads Get not threaded index
     * @param bool    $no_search  Get index not limited to search result (optionally)
     *
     * @return rcube_result_index|rcube_result_thread List of messages (UIDs)
     */
    #[Override]
    public function index($folder = null, $sort_field = null, $sort_order = null,
        $no_threads = false, $no_search = false
    ) {
        if (!$no_threads && $this->threading) {
            return $this->thread_index($folder, $sort_field, $sort_order);
        }

        $this->set_sort_order($sort_field, $sort_order);

        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        // we have a saved search result, get index from there
        if ($this->search_string) {
            if ($this->search_set->is_empty()) {
                return new rcube_result_index($folder, '* SORT');
            }

            if ($this->search_set instanceof rcube_result_multifolder) {
                $index = $this->search_set;
                $index->folder = $folder;
            // TODO: handle changed sorting (>> reindent once https://github.com/PHP-CS-Fixer/PHP-CS-Fixer/issues/7497 is fixed)
            }
            // search result is an index with the same sorting?
            elseif (($this->search_set instanceof rcube_result_index)
                && ((!$this->sort_field && !$this->search_sorted)
                    || ($this->search_sorted && $this->search_sort_field == $this->sort_field))
            ) {
                $index = $this->search_set;
            }
            // $no_search is enabled when we are not interested in
            // fetching index for search result, e.g. to sort
            // threaded search result we can use full mailbox index.
            // This makes possible to use index from cache
            elseif (!$no_search) {
                if (!$this->sort_field) {
                    // No sorting needed, just build index from the search result
                    // @TODO: do we need to sort by UID here?
                    $search = $this->search_set->get_compressed();
                    $index = new rcube_result_index($folder, '* ESEARCH ALL ' . $search);
                } else {
                    $index = $this->index_direct($folder, $this->sort_field, $this->sort_order, $this->search_set);
                }
            }

            if (isset($index)) {
                if ($this->sort_order != $index->get_parameters('ORDER')) {
                    $index->revert();
                }

                return $index;
            }
        }

        // check local cache
        if ($mcache = $this->get_mcache_engine()) {
            return $mcache->get_index($folder, $this->sort_field, $this->sort_order);
        }

        // fetch from IMAP server
        return $this->index_direct($folder, $this->sort_field, $this->sort_order);
    }

    /**
     * Return sorted list of message UIDs ignoring current search settings.
     * Doesn't uses cache by default.
     *
     * @param string             $folder     Folder to get index from
     * @param string             $sort_field Sort column
     * @param string             $sort_order Sort order [ASC, DESC]
     * @param rcube_result_index $search     Optional messages set to limit the result
     *
     * @return rcube_result_index Sorted list of message UIDs
     */
    public function index_direct($folder, $sort_field = null, $sort_order = null, $search = null)
    {
        if (!empty($search)) {
            $search = $search->get_compressed();
        }

        // use message index sort as default sorting
        if (!$sort_field) {
            // use search result from count() if possible
            if (empty($search) && $this->options['skip_deleted']
                && !empty($this->icache['undeleted_idx'])
                && $this->icache['undeleted_idx']->get_parameters('ALL') !== null
                && $this->icache['undeleted_idx']->get_parameters('MAILBOX') == $folder
            ) {
                $index = $this->icache['undeleted_idx'];
            } elseif (!$this->check_connection()) {
                return new rcube_result_index();
            } else {
                $query = $this->options['skip_deleted'] ? 'UNDELETED' : '';
                if ($search) {
                    $query = trim($query . ' UID ' . $search);
                }

                $index = $this->conn->search($folder, $query, true);
            }
        } elseif (!$this->check_connection()) {
            return new rcube_result_index();
        }
        // fetch complete message index
        else {
            if ($this->get_capability('SORT')) {
                $query = $this->options['skip_deleted'] ? 'UNDELETED' : '';
                if ($search) {
                    $query = trim($query . ' UID ' . $search);
                }

                $index = $this->conn->sort($folder, $sort_field, $query, true);
            }

            if (empty($index) || $index->is_error()) {
                $index = $this->conn->index($folder, $search ?: '1:*',
                    $sort_field, $this->options['skip_deleted'],
                    $search ? true : false, true);
            }
        }

        if ($sort_order != $index->get_parameters('ORDER')) {
            $index->revert();
        }

        return $index;
    }

    /**
     * Return index of threaded message UIDs
     *
     * @param ?string $folder     Folder to get index from
     * @param string  $sort_field Sort column
     * @param string  $sort_order Sort order [ASC, DESC]
     *
     * @return rcube_result_thread Message UIDs
     */
    public function thread_index($folder = null, $sort_field = null, $sort_order = null)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        // we have a saved search result, get index from there
        if ($this->search_string && $this->search_threads && $folder == $this->folder) {
            $threads = $this->search_set;
        } else {
            // get all threads (default sort order)
            $threads = $this->threads($folder);
        }

        $this->set_sort_order($sort_field, $sort_order);
        $this->sort_threads($threads);

        return $threads;
    }

    /**
     * Sort threaded result, using THREAD=REFS method if available.
     * If not, use any method and re-sort the result in THREAD=REFS way.
     *
     * @param rcube_result_thread $threads Threads result set
     */
    protected function sort_threads($threads)
    {
        if ($threads->is_empty()) {
            return;
        }

        // THREAD=ORDEREDSUBJECT: sorting by sent date of root message
        // THREAD=REFERENCES:     sorting by sent date of root message
        // THREAD=REFS:           sorting by the most recent date in each thread

        if ($this->threading != 'REFS' || ($this->sort_field && $this->sort_field != 'date')) {
            $sortby = $this->sort_field ?: 'date';
            $index = $this->index($this->folder, $sortby, $this->sort_order, true, true);

            if (!$index->is_empty()) {
                $threads->sort($index);
            }
        } elseif ($this->sort_order != $threads->get_parameters('ORDER')) {
            $threads->revert();
        }
    }

    /**
     * Invoke search request to IMAP server
     *
     * @param string|array $folder     Folder name(s) to search in
     * @param string       $search     Search criteria
     * @param string       $charset    Search charset
     * @param string       $sort_field Header field to sort by
     *
     * @return rcube_result_index Search result object
     *
     * @todo: Search criteria should be provided in non-IMAP format, e.g. array
     */
    #[Override]
    public function search($folder = '', $search = 'ALL', $charset = null, $sort_field = null)
    {
        if (!$search) {
            $search = 'ALL';
        }

        if ((is_array($folder) && empty($folder)) || (!is_array($folder) && !strlen($folder))) {
            $folder = $this->folder;
        }

        $plugin = $this->plugins->exec_hook('imap_search_before', [
            'folder' => $folder,
            'search' => $search,
            'charset' => $charset,
            'sort_field' => $sort_field,
            'threading' => $this->threading,
            'result' => null,
        ]);

        $folder = $plugin['folder'];
        $search = $plugin['search'];
        $charset = $plugin['charset'];
        $sort_field = $plugin['sort_field'];
        $results = $plugin['result'];

        // multi-folder search
        if (!$results && is_array($folder) && count($folder) > 1 && $search != 'ALL') {
            // connect IMAP to have all the required classes and settings loaded
            $this->check_connection();

            // disable threading
            $this->threading = false;

            $searcher = new rcube_imap_search($this->options, $this->conn);

            // set limit to not exceed the client's request timeout
            $searcher->set_timelimit(60);

            // continue existing incomplete search
            if (!empty($this->search_set) && $this->search_set->incomplete && $search == $this->search_string) {
                $searcher->set_results($this->search_set);
            }

            // execute the search
            $results = $searcher->exec(
                $folder,
                $search,
                $charset ?: $this->default_charset,
                $sort_field && $this->get_capability('SORT') ? $sort_field : null,
                $this->threading
            );
        } elseif (!$results) {
            $folder = is_array($folder) ? $folder[0] : $folder;
            $search = is_array($search) ? $search[$folder] : $search;
            $results = $this->search_index($folder, $search, $charset, $sort_field);
        }

        $sorted = $this->threading || $this->search_sorted || !empty($plugin['search_sorted']);

        $this->set_search_set([$search, $results, $charset, $sort_field, $sorted]);

        return $results;
    }

    /**
     * Direct (real and simple) SEARCH request (without result sorting and caching).
     *
     * @param array|string|null $folder Mailbox name to search in
     * @param string            $str    Search string
     *
     * @return rcube_result_index|rcube_result_multifolder Search result (UIDs)
     */
    #[Override]
    public function search_once($folder = null, $str = 'ALL')
    {
        if (!$this->check_connection()) {
            return new rcube_result_index();
        }

        if (!$str) {
            $str = 'ALL';
        }

        // multi-folder search
        if (is_array($folder) && count($folder) > 1) {
            $searcher = new rcube_imap_search($this->options, $this->conn);
            $index = $searcher->exec($folder, $str, $this->default_charset);
        } else {
            $folder = is_array($folder) ? $folder[0] : $folder;
            if (!strlen($folder)) {
                $folder = $this->folder;
            }
            $index = $this->conn->search($folder, $str, true);
        }

        return $index;
    }

    /**
     * protected search method
     *
     * @param string $folder     Folder name
     * @param string $criteria   Search criteria
     * @param string $charset    Charset
     * @param string $sort_field Sorting field
     *
     * @return rcube_result_index|rcube_result_thread Search results (UIDs)
     *
     * @see rcube_imap::search()
     */
    protected function search_index($folder, $criteria = 'ALL', $charset = null, $sort_field = null)
    {
        if (!$this->check_connection()) {
            if ($this->threading) {
                return new rcube_result_thread();
            }

            return new rcube_result_index();
        }

        if ($this->options['skip_deleted'] && !preg_match('/UNDELETED/', $criteria)) {
            $criteria = 'UNDELETED ' . $criteria;
        }

        // unset CHARSET if criteria string is ASCII, this way
        // SEARCH won't be re-sent after "unsupported charset" response
        if ($charset && $charset != 'US-ASCII' && is_ascii($criteria)) {
            $charset = 'US-ASCII';
        }

        if ($this->threading) {
            $threads = $this->conn->thread($folder, $this->threading, $criteria, true, $charset);

            // Error, try with US-ASCII (RFC5256: SORT/THREAD must support US-ASCII and UTF-8,
            // but I've seen that Courier doesn't support UTF-8)
            if ($threads->is_error() && $charset && $charset != 'US-ASCII') {
                $threads = $this->conn->thread($folder, $this->threading,
                    self::convert_criteria($criteria, $charset), true, 'US-ASCII');
            }

            return $threads;
        }

        if ($sort_field && $this->get_capability('SORT')) {
            $charset = $charset ?: $this->default_charset;
            $messages = $this->conn->sort($folder, $sort_field, $criteria, true, $charset);

            // Error, try with US-ASCII (RFC5256: SORT/THREAD must support US-ASCII and UTF-8,
            // but I've seen Courier with disabled UTF-8 support)
            if ($messages->is_error() && $charset && $charset != 'US-ASCII') {
                $messages = $this->conn->sort($folder, $sort_field,
                    self::convert_criteria($criteria, $charset), true, 'US-ASCII');
            }

            if (!$messages->is_error()) {
                $this->search_sorted = true;
                return $messages;
            }
        }

        $messages = $this->conn->search($folder,
            ($charset && $charset != 'US-ASCII' ? "CHARSET {$charset} " : '') . $criteria, true);

        // Error, try with US-ASCII (some servers may support only US-ASCII)
        if ($messages->is_error() && $charset && $charset != 'US-ASCII') {
            $messages = $this->conn->search($folder, self::convert_criteria($criteria, $charset), true);
        }

        $this->search_sorted = false;

        return $messages;
    }

    /**
     * Converts charset of search criteria string
     *
     * @param string $str          Search string
     * @param string $charset      Original charset
     * @param string $dest_charset Destination charset (default US-ASCII)
     *
     * @return string Search string
     */
    public static function convert_criteria($str, $charset, $dest_charset = 'US-ASCII')
    {
        // convert strings to US_ASCII
        if (preg_match_all('/\{([0-9]+)\}\r\n/', $str, $matches, \PREG_OFFSET_CAPTURE)) {
            $last = 0;
            $res = '';

            foreach ($matches[1] as $m) {
                $string_offset = $m[1] + strlen($m[0]) + 4; // {}\r\n
                $string = substr($str, $string_offset - 1, $m[0]);
                $string = rcube_charset::convert($string, $charset, $dest_charset);

                if (!strlen($string)) {
                    continue;
                }

                $res .= substr($str, $last, $m[1] - $last - 1) . rcube_imap_generic::escape($string);
                $last = intval($m[0]) + $string_offset - 1;
            }

            if ($last < strlen($str)) {
                $res .= substr($str, $last, strlen($str) - $last);
            }
        }
        // strings for conversion not found
        else {
            $res = $str;
        }

        return $res;
    }

    /**
     * Refresh saved search set
     *
     * @return array Current search set
     */
    #[Override]
    public function refresh_search()
    {
        if (!empty($this->search_string)) {
            $this->search(
                is_object($this->search_set) ? $this->search_set->get_parameters('MAILBOX') : '',
                $this->search_string,
                $this->search_charset,
                $this->search_sort_field
            );
        }

        return $this->get_search_set();
    }

    /**
     * Flag certain result subsets as 'incomplete'.
     * For subsequent refresh_search() calls to only refresh the updated parts.
     */
    protected function set_search_dirty($folder)
    {
        if ($this->search_set && is_a($this->search_set, 'rcube_result_multifolder')) {
            if ($subset = $this->search_set->get_set($folder)) {
                $subset->incomplete = $this->search_set->incomplete = true;
            }
        }
    }

    /**
     * Return message headers object of a specific message
     *
     * @param int    $uid    Message UID
     * @param string $folder Folder to read from
     * @param bool   $force  True to skip cache
     *
     * @return rcube_message_header|false Message headers, False on error
     */
    #[Override]
    public function get_message_headers($uid, $folder = null, $force = false)
    {
        // decode combined UID-folder identifier
        if (preg_match('/^\d+-.+/', $uid)) {
            [$uid, $folder] = explode('-', $uid, 2);
        }

        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        // get cached headers
        if (!$force && $uid && ($mcache = $this->get_mcache_engine())) {
            $headers = $mcache->get_message($folder, $uid);
        } elseif (!$this->check_connection()) {
            $headers = false;
        } else {
            $headers = $this->conn->fetchHeader(
                $folder, $uid, true, true, $this->get_fetch_headers(), $this->get_fetch_items());

            if (is_object($headers)) {
                $headers->folder = $folder;
            }
        }

        return $headers;
    }

    /**
     * Fetch message headers and body structure from the IMAP server and build
     * an object structure.
     *
     * @param int    $uid    Message UID to fetch
     * @param string $folder Folder to read from
     *
     * @return rcube_message_header|false Message data, False on error
     */
    #[Override]
    public function get_message($uid, $folder = null)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        // decode combined UID-folder identifier
        if (preg_match('/^\d+-.+/', $uid)) {
            [$uid, $folder] = explode('-', $uid, 2);
        }

        // Check internal cache
        if (isset($this->icache['message']) && ($headers = $this->icache['message'])) {
            // Make sure the folder and UID is what we expect.
            // In case when the same process works with folders that are personal
            // and shared two folders can contain the same UIDs.
            if ($headers->uid == $uid && $headers->folder == $folder) {
                return $headers;
            }
        }

        $headers = $this->get_message_headers($uid, $folder);

        // message doesn't exist?
        if (empty($headers)) {
            return false;
        }

        // structure might be cached
        if (!empty($headers->structure)) {
            return $headers;
        }

        $this->msg_uid = $uid;

        if (!$this->check_connection()) {
            return $headers;
        }

        if (empty($headers->bodystructure)) {
            $headers->bodystructure = $this->conn->getStructure($folder, $uid, true);
        }

        $structure = $headers->bodystructure;

        if (empty($structure)) {
            return $headers;
        }

        // set message charset from message headers
        if ($headers->charset) {
            $this->struct_charset = $headers->charset;
        } else {
            $this->struct_charset = $this->structure_charset($structure);
        }

        $headers->ctype = @strtolower($headers->ctype);

        // Here we can recognize malformed BODYSTRUCTURE and
        // 1. [@TODO] parse the message in other way to create our own message structure
        // 2. or just show the raw message body.
        // Example of structure for malformed MIME message:
        // ("text" "plain" NIL NIL NIL "7bit" 2154 70 NIL NIL NIL)
        if ($headers->ctype && !is_array($structure[0]) && $headers->ctype != 'text/plain'
            && strtolower($structure[0] . '/' . $structure[1]) == 'text/plain'
        ) {
            // A special known case "Content-type: text" (#1488968)
            if ($headers->ctype == 'text') {
                $structure[1] = 'plain';
                $headers->ctype = 'text/plain';
            }
            // we can handle single-part messages, by simple fix in structure (#1486898)
            elseif (preg_match('/^(text|application)\/(.*)/', $headers->ctype, $m)) {
                $structure[0] = $m[1];
                $structure[1] = $m[2];
            } else {
                // Try to parse the message using rcube_mime_decode.
                // We need a better solution, it parses message
                // in memory, which wouldn't work for very big messages,
                // (it uses up to 10x more memory than the message size)
                // it's also buggy and not actively developed
                if ($headers->size && rcube_utils::mem_check($headers->size * 10)) {
                    $raw_msg = $this->get_raw_body($uid);
                    $struct = rcube_mime::parse_message($raw_msg);
                } else {
                    return $headers;
                }
            }
        }

        if (empty($struct)) {
            $struct = $this->structure_part($structure, 0, '', $headers);
        }

        // some workarounds on simple messages...
        if (empty($struct->parts)) {
            // ...don't trust given content-type
            if (!empty($headers->ctype)) {
                $struct->mime_id = '1';
                $struct->mimetype = strtolower($headers->ctype);
                [$struct->ctype_primary, $struct->ctype_secondary] = explode('/', $struct->mimetype);
            }

            // ...and charset (there's a case described in #1488968 where invalid content-type
            // results in invalid charset in BODYSTRUCTURE)
            if (!empty($headers->charset) && $headers->charset != $struct->ctype_parameters['charset']) {
                $struct->charset = $headers->charset;
                $struct->ctype_parameters['charset'] = $headers->charset;
            }
        }

        $headers->structure = $struct;

        return $this->icache['message'] = $headers;
    }

    /**
     * Build message part object
     *
     * @param array  $part
     * @param int    $count
     * @param string $parent
     */
    protected function structure_part($part, $count = 0, $parent = '', $mime_headers = null)
    {
        $struct = new rcube_message_part();
        $struct->mime_id = empty($parent) ? (string) $count : "{$parent}.{$count}";

        // multipart
        if (is_array($part[0])) {
            $struct->ctype_primary = 'multipart';

            /* RFC3501: BODYSTRUCTURE fields of multipart part
            part1 array
            part2 array
            part3 array
            ....
            1. subtype
            2. parameters (optional)
            3. description (optional)
            4. language (optional)
            5. location (optional)
            */

            // find first non-array entry
            for ($i = 1; $i < count($part); $i++) {
                if (is_string($part[$i])) {
                    $struct->ctype_secondary = strtolower($part[$i]);

                    // read content type parameters
                    if (isset($part[$i + 1]) && is_array($part[$i + 1])) {
                        $struct->ctype_parameters = [];
                        for ($j = 0; $j < count($part[$i + 1]); $j += 2) {
                            if (is_string($part[$i + 1][$j])) {
                                $param = strtolower($part[$i + 1][$j]);
                                $struct->ctype_parameters[$param] = $part[$i + 1][$j + 1];
                            }
                        }
                    }

                    break;
                }
            }

            $struct->mimetype = 'multipart/' . $struct->ctype_secondary;
            $mime_part_headers = [];

            // build parts list for headers pre-fetching
            for ($i = 0; $i < count($part); $i++) {
                // fetch message headers if message/rfc822 or named part
                if (is_array($part[$i]) && !is_array($part[$i][0])) {
                    $tmp_part_id = $struct->mime_id ? $struct->mime_id . '.' . ($i + 1) : strval($i + 1);
                    if (strtolower($part[$i][0]) == 'message' && strtolower($part[$i][1]) == 'rfc822') {
                        $mime_part_headers[] = $tmp_part_id;
                    } elseif ($this->is_attachment_part($part[$i])) {
                        $mime_part_headers[] = $tmp_part_id;
                    }
                }
            }

            // pre-fetch headers of all parts (in one command for better performance)
            // @TODO: we could do this before _structure_part() call, to fetch
            // headers for parts on all levels
            if (!empty($mime_part_headers)) {
                $mime_part_headers = $this->conn->fetchMIMEHeaders($this->folder,
                    $this->msg_uid, $mime_part_headers);
            }

            $struct->parts = [];
            $count = 0;

            for ($i = 0; $i < count($part); $i++) {
                if (!is_array($part[$i])) {
                    break;
                }
                $tmp_part_id = $struct->mime_id ? $struct->mime_id . '.' . ($i + 1) : strval($i + 1);
                $struct->parts[] = $this->structure_part($part[$i], ++$count, $struct->mime_id,
                    !empty($mime_part_headers[$tmp_part_id]) ? $mime_part_headers[$tmp_part_id] : null);
            }

            return $struct;
        }

        /* RFC3501: BODYSTRUCTURE fields of non-multipart part
            0. type
            1. subtype
            2. parameters
            3. id
            4. description
            5. encoding
            6. size
          -- text
            7. lines
          -- message/rfc822
            7. envelope structure
            8. body structure
            9. lines
          --
            x. md5 (optional)
            x. disposition (optional)
            x. language (optional)
            x. location (optional)
        */

        // regular part
        // Note: If the BODYSTRUCTURE is invalid index 0 and 1 can be NULL (#9896)
        if (is_array($part[1])) {
            $struct->ctype_primary = 'multipart';
            $struct->ctype_secondary = isset($part[0]) ? strtolower($part[0]) : 'mixed';
        } else {
            $struct->ctype_primary = isset($part[0]) ? strtolower($part[0]) : 'text';
            $struct->ctype_secondary = isset($part[1]) ? strtolower($part[1]) : 'plain';
        }

        $struct->mimetype = $struct->ctype_primary . '/' . $struct->ctype_secondary;

        // Sometimes it might be: 0. subtype, 1. parameters, ...
        $params_idx = is_array($part[1]) ? 1 : 2;

        // read content type parameters
        if (is_array($part[$params_idx])) {
            $struct->ctype_parameters = [];
            for ($i = 0; $i < count($part[$params_idx]); $i += 2) {
                if (is_string($part[$params_idx][$i])) {
                    $struct->ctype_parameters[strtolower($part[$params_idx][$i])] = $part[$params_idx][$i + 1];
                }
            }

            if (isset($struct->ctype_parameters['charset'])) {
                $struct->charset = $struct->ctype_parameters['charset'];
            }
        }

        // #1487700: workaround for lack of charset in malformed structure
        if (empty($struct->charset) && !empty($mime_headers) && !empty($mime_headers->charset)) {
            $struct->charset = $mime_headers->charset;
        }

        // Sanitize charset for security
        if (!rcube_charset::is_valid($struct->charset)) {
            $struct->charset = '';
        }

        // read content encoding
        if (!empty($part[5]) && !is_array($part[5])) {
            $struct->encoding = strtolower($part[5]);
            $struct->headers['content-transfer-encoding'] = $struct->encoding;
        }

        // get part size
        if (!empty($part[6])) {
            $struct->size = intval($part[6]);
        }

        // read part disposition
        $di = 8;
        if ($struct->ctype_primary == 'text') {
            $di++;
        } elseif ($struct->mimetype == 'message/rfc822') {
            $di += 3;
        }

        if (isset($part[$di]) && is_array($part[$di]) && count($part[$di]) == 2) {
            $struct->disposition = strtolower($part[$di][0]);
            if ($struct->disposition && $struct->disposition !== 'inline' && $struct->disposition !== 'attachment') {
                // RFC2183, Section 2.8 - unrecognized type should be treated as "attachment"
                $struct->disposition = 'attachment';
            }
            if (is_array($part[$di][1])) {
                for ($n = 0; $n < count($part[$di][1]); $n += 2) {
                    if (is_string($part[$di][1][$n])) {
                        $struct->d_parameters[strtolower($part[$di][1][$n])] = $part[$di][1][$n + 1];
                    }
                }
            }
        }

        // get message/rfc822's child-parts
        if (isset($part[8]) && is_array($part[8]) && $di != 8) {
            $struct->parts = [];
            $mime_part_headers = [];

            for ($i = 0; $i < count($part[8]); $i++) {
                if (!is_array($part[8][$i])) {
                    break;
                }

                $subpart_id = $struct->mime_id ? $struct->mime_id . '.' . ($i + 1) : strval($i + 1);

                if ($this->is_attachment_part($part[8][$i])) {
                    $mime_part_headers[] = $subpart_id;
                }

                $struct->parts[$subpart_id] = $part[8][$i];
            }

            // Fetch attachment parts' headers in one go
            if (!empty($mime_part_headers)) {
                $mime_part_headers = $this->conn->fetchMIMEHeaders($this->folder, $this->msg_uid, $mime_part_headers);
            }

            $count = 0;
            foreach ($struct->parts as $idx => $subpart) {
                $struct->parts[$idx] = $this->structure_part($subpart, ++$count, $struct->mime_id,
                    !empty($mime_part_headers[$idx]) ? $mime_part_headers[$idx] : null);
            }

            $struct->parts = array_values($struct->parts);
        }

        // get part ID
        if (!empty($part[3])) {
            $struct->content_id = $struct->headers['content-id'] = trim($part[3]);

            // FIXME: This is not the best idea. We should get rid of this at some point
            if (empty($struct->disposition)) {
                $struct->disposition = 'inline';
            }
        }

        // fetch message headers if message/rfc822 or image or named part (could contain Content-Location header)
        if (
            empty($mime_headers)
            && (
                $struct->ctype_primary == 'message' || $struct->ctype_primary == 'image'
                || (!empty($struct->ctype_parameters['name']) && !empty($struct->content_id))
            )
        ) {
            $mime_headers = $this->conn->fetchPartHeader($this->folder, $this->msg_uid, true, $struct->mime_id);
        }

        if (!empty($mime_headers)) {
            if (is_string($mime_headers)) {
                $struct->headers = rcube_mime::parse_headers($mime_headers) + $struct->headers;
            } elseif (is_object($mime_headers)) {
                $struct->headers = get_object_vars($mime_headers) + $struct->headers;
            }
        }

        // get real content-type of message/rfc822
        if ($struct->mimetype == 'message/rfc822') {
            // single-part
            if (!is_array($part[8][0]) && !is_array($part[8][1])) {
                $struct->real_mimetype = strtolower($part[8][0] . '/' . $part[8][1]);
            }
            // multi-part
            else {
                for ($n = 0; $n < count($part[8]); $n++) {
                    if (!is_array($part[8][$n])) {
                        break;
                    }
                }
                $struct->real_mimetype = 'multipart/' . strtolower($part[8][$n]);
            }
        }

        if ($struct->ctype_primary == 'message' && empty($struct->parts)) {
            if (is_array($part[8]) && $di != 8) {
                $struct->parts[] = $this->structure_part($part[8], ++$count, $struct->mime_id);
            }
        }

        // normalize filename property
        $struct->normalize($mime_headers);

        return $struct;
    }

    /**
     * Check if the mail structure part is an attachment part and requires
     * fetching the MIME headers for further processing.
     */
    protected function is_attachment_part($part)
    {
        if (!empty($part[2]) && is_array($part[2]) && empty($part[3])) {
            $params = array_map('strtolower', array_filter($part[2], 'is_string'));
            $find = ['name', 'filename', 'name*', 'filename*', 'name*0', 'filename*0', 'name*0*', 'filename*0*'];

            // In case of malformed header check disposition. E.g. some servers for
            // "Content-Type: PDF; name=test.pdf" may return text/plain and ignore name argument
            return count(array_intersect($params, $find)) > 0
                || (isset($part[9]) && is_array($part[9]) && stripos($part[9][0], 'attachment') === 0);
        }

        return false;
    }

    /**
     * Get charset name from message structure (first part)
     *
     * @param array $structure Message structure
     *
     * @return ?string Charset name
     */
    protected function structure_charset($structure)
    {
        while (is_array($structure)) {
            if (isset($structure[2]) && is_array($structure[2]) && $structure[2][0] == 'charset') {
                return $structure[2][1];
            }

            $structure = $structure[0];
        }

        return null;
    }

    /**
     * Fetch message body of a specific message from the server
     *
     * @param int                 $uid               Message UID
     * @param string              $part              Part number
     * @param ?rcube_message_part $o_part            Part object created by get_structure()
     * @param mixed               $print             True to print part, resource to write part contents in
     * @param resource            $fp                File pointer to save the message part
     * @param bool                $skip_charset_conv Disables charset conversion
     * @param int                 $max_bytes         Only read this number of bytes
     * @param bool                $formatted         Enables formatting of text/* parts bodies
     *
     * @return string|bool Message/part body if not printed
     */
    #[Override]
    public function get_message_part($uid, $part, $o_part = null, $print = null, $fp = null,
        $skip_charset_conv = false, $max_bytes = 0, $formatted = true)
    {
        if (!$this->check_connection()) {
            return false;
        }

        // get part data if not provided
        if (!is_object($o_part)) {
            $structure = $this->conn->getStructure($this->folder, $uid, true);
            $part_data = rcube_imap_generic::getStructurePartData($structure, $part);

            if (empty($part_data)) {
                return false;
            }

            $o_part = new rcube_message_part();
            $o_part->ctype_primary = $part_data['type'] ?? null;
            $o_part->ctype_secondary = $part_data['subtype'] ?? null;
            $o_part->encoding = $part_data['encoding'] ?? null;
            $o_part->charset = $part_data['charset'] ?? null;
            $o_part->size = $part_data['size'] ?? 0;
        }

        $body = '';

        // Note: multipart/* parts will have size=0, we don't want to ignore them
        if ($o_part->size || $o_part->ctype_primary == 'multipart') {
            $formatted = $formatted && $o_part->ctype_primary == 'text';
            $body = $this->conn->handlePartBody($this->folder, $uid, true,
                $part ?: 'TEXT', $o_part->encoding, $print, $fp, $formatted, $max_bytes);
        }

        if ($fp || $print) {
            return true;
        }

        // convert charset (if text or message part)
        if ($body && preg_match('/^(text|message)$/', $o_part->ctype_primary)) {
            // Remove NULL characters if any (#1486189)
            if ($formatted && strpos($body, "\x00") !== false) {
                $body = str_replace("\x00", '', $body);
            }

            if (!$skip_charset_conv) {
                if (!$o_part->charset || strtoupper($o_part->charset) == 'US-ASCII') {
                    // try to extract charset information from HTML meta tag (#1488125)
                    if ($o_part->ctype_secondary == 'html' && preg_match('/<meta[^>]+charset=([a-z0-9-_]+)/i', $body, $m)) {
                        $o_part->charset = strtoupper($m[1]);
                    } else {
                        $o_part->charset = $this->default_charset;
                    }
                }

                $body = rcube_charset::convert($body, $o_part->charset);
            }
        }

        return $body;
    }

    /**
     * Returns the whole message source as string (or saves to a file)
     *
     * @param int      $uid  Message UID
     * @param resource $fp   File pointer to save the message
     * @param string   $part Optional message part ID
     *
     * @return string|false Message source string
     */
    #[Override]
    public function get_raw_body($uid, $fp = null, $part = null)
    {
        if (!$this->check_connection()) {
            return false;
        }

        return $this->conn->handlePartBody($this->folder, $uid,
            true, $part, null, false, $fp);
    }

    /**
     * Returns the message headers as string
     *
     * @param int    $uid  Message UID
     * @param string $part Optional message part ID
     *
     * @return string|false Message headers string
     */
    #[Override]
    public function get_raw_headers($uid, $part = null)
    {
        if (!$this->check_connection()) {
            return false;
        }

        return $this->conn->fetchPartHeader($this->folder, $uid, true, $part);
    }

    /**
     * Sends the whole message source to stdout
     *
     * @param int  $uid       Message UID
     * @param bool $formatted Enables line-ending formatting
     */
    #[Override]
    public function print_raw_body($uid, $formatted = true)
    {
        if (!$this->check_connection()) {
            return;
        }

        $this->conn->handlePartBody($this->folder, $uid, true, null, null, true, null, $formatted);
    }

    /**
     * Set message flag to one or several messages
     *
     * @param mixed  $uids       Message UIDs as array or comma-separated string, or '*'
     * @param string $flag       Flag to set: SEEN, UNDELETED, DELETED, RECENT, ANSWERED, DRAFT, MDNSENT
     * @param string $folder     Folder name
     * @param bool   $skip_cache True to skip message cache clean up
     *
     * @return bool Operation status
     */
    #[Override]
    public function set_flag($uids, $flag, $folder = null, $skip_cache = false)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $flag = strtoupper($flag);
        [$uids, $all_mode] = $this->parse_uids($uids);

        if (str_starts_with($flag, 'UN')) {
            $result = $this->conn->unflag($folder, $uids, substr($flag, 2));
        } else {
            $result = $this->conn->flag($folder, $uids, $flag);
        }

        if ($result && !$skip_cache) {
            // reload message headers if cached
            // update flags instead removing from cache
            if ($mcache = $this->get_mcache_engine()) {
                $status = !str_starts_with($flag, 'UN');
                $mflag = preg_replace('/^UN/', '', $flag);
                $mcache->change_flag($folder, $all_mode ? null : explode(',', $uids),
                    $mflag, $status);
            }

            // clear cached counters
            if ($flag == 'SEEN' || $flag == 'UNSEEN') {
                $this->clear_messagecount($folder, ['SEEN', 'UNSEEN']);
            } elseif ($flag == 'DELETED' || $flag == 'UNDELETED') {
                $this->clear_messagecount($folder, ['ALL', 'THREADS']);
                if ($this->options['skip_deleted']) {
                    // remove cached messages
                    $this->clear_message_cache($folder, $all_mode ? null : explode(',', $uids));
                }
            }

            $this->set_search_dirty($folder);
        }

        unset($this->icache['message']);

        return $result;
    }

    /**
     * Append a mail message (source) to a specific folder
     *
     * @param ?string      $folder  Target folder
     * @param string|array $message The message source string or filename
     *                              or array (of strings and file pointers)
     * @param string       $headers Headers string if $message contains only the body
     * @param bool         $is_file True if $message is a filename
     * @param array        $flags   Message flags
     * @param mixed        $date    Message internal date
     * @param bool         $binary  Enables BINARY append
     *
     * @return int|bool Appended message UID or True on success, False on error
     */
    #[Override]
    public function save_message($folder, &$message, $headers = '', $is_file = false, $flags = [], $date = null, $binary = false)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        if (!$this->check_connection()) {
            return false;
        }

        // make sure folder exists
        if (!$this->folder_exists($folder)) {
            return false;
        }

        $date = $this->date_format($date);

        if ($is_file) {
            $saved = $this->conn->appendFromFile($folder, $message, $headers, $flags, $date, $binary);
        } else {
            $saved = $this->conn->append($folder, $message, $flags, $date, $binary);
        }

        if ($saved) {
            // increase messagecount of the target folder
            $this->set_messagecount($folder, 'ALL', 1);

            $this->plugins->exec_hook('message_saved', [
                'folder' => $folder,
                'message' => $message,
                'headers' => $headers,
                'is_file' => $is_file,
                'flags' => $flags,
                'date' => $date,
                'binary' => $binary,
                'result' => $saved,
            ]);
        }

        return $saved;
    }

    /**
     * Move a message from one folder to another
     *
     * @param mixed  $uids      Message UIDs as array or comma-separated string, or '*'
     * @param string $to_mbox   Target folder
     * @param string $from_mbox Source folder
     *
     * @return bool True on success, False on error
     */
    #[Override]
    public function move_message($uids, $to_mbox, $from_mbox = '')
    {
        if (!strlen($from_mbox)) {
            $from_mbox = $this->folder;
        }

        if ($to_mbox === $from_mbox) {
            return false;
        }

        [$uids, $all_mode] = $this->parse_uids($uids);

        // exit if no message uids are specified
        if (empty($uids)) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $plugin = $this->plugins->exec_hook('message_move', ['source_folder' => $from_mbox, 'target_folder' => $to_mbox, 'uids' => $uids]);
        if ($plugin['abort']) {
            return false;
        }

        $config = rcube::get_instance()->config;
        $to_trash = $to_mbox == $config->get('trash_mbox');

        // flag messages as read before moving them
        if ($to_trash && $config->get('read_when_deleted')) {
            // don't flush cache (4th argument)
            $this->set_flag($uids, 'SEEN', $from_mbox, true);
        }

        // move messages
        $moved = $this->conn->move($uids, $from_mbox, $to_mbox);

        // when moving to Trash we make sure the folder exists
        // as it's uncommon scenario we do this when MOVE fails, not before
        if (!$moved && $to_trash && $this->get_response_code() == rcube_storage::TRYCREATE) {
            if ($this->create_folder($to_mbox, true, 'trash')) {
                $moved = $this->conn->move($uids, $from_mbox, $to_mbox);
            }
        }

        if ($moved) {
            $this->clear_messagecount($from_mbox);
            $this->clear_messagecount($to_mbox);
            $this->set_search_dirty($from_mbox);
            $this->set_search_dirty($to_mbox);

            // unset internal cache
            unset($this->icache['threads']);
            unset($this->icache['message']);

            // remove message ids from search set
            if ($this->search_set && $from_mbox == $this->folder) {
                // threads are too complicated to just remove messages from set
                if ($this->search_threads || $all_mode) {
                    $this->refresh_search();
                } elseif (!$this->search_set->incomplete) {
                    $this->search_set->filter(explode(',', $uids), $this->folder);
                }
            }

            // remove cached messages
            // @TODO: do cache update instead of clearing it
            $this->clear_message_cache($from_mbox, $all_mode ? null : explode(',', $uids));
        }

        return $moved;
    }

    /**
     * Copy a message from one folder to another
     *
     * @param mixed  $uids      Message UIDs as array or comma-separated string, or '*'
     * @param string $to_mbox   Target folder
     * @param string $from_mbox Source folder
     *
     * @return bool True on success, False on error
     */
    #[Override]
    public function copy_message($uids, $to_mbox, $from_mbox = '')
    {
        if (!strlen($from_mbox)) {
            $from_mbox = $this->folder;
        }

        [$uids] = $this->parse_uids($uids);

        // exit if no message uids are specified
        if (empty($uids)) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        // copy messages
        $copied = $this->conn->copy($uids, $from_mbox, $to_mbox);

        if ($copied) {
            $this->clear_messagecount($to_mbox);
        }

        return $copied;
    }

    /**
     * Mark messages as deleted and expunge them
     *
     * @param array|string $uids   Message UIDs as array or comma-separated string, or '*'
     * @param ?string      $folder Source folder
     *
     * @return bool True on success, False on error
     */
    #[Override]
    public function delete_message($uids, $folder = null)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        [$uids, $all_mode] = $this->parse_uids($uids);

        // exit if no message uids are specified
        if (empty($uids)) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $plugin = $this->plugins->exec_hook('message_delete', ['folder' => $folder, 'uids' => $uids]);
        if ($plugin['abort']) {
            return false;
        }

        $deleted = $this->conn->flag($folder, $uids, 'DELETED');

        if ($deleted) {
            // send expunge command in order to have the deleted message
            // really deleted from the folder
            $this->expunge_message($uids, $folder, false);
            $this->clear_messagecount($folder);

            // unset internal cache
            unset($this->icache['threads']);
            unset($this->icache['message']);

            $this->set_search_dirty($folder);

            // remove message ids from search set
            if ($this->search_set && $folder == $this->folder) {
                // threads are too complicated to just remove messages from set
                if ($this->search_threads || $all_mode) {
                    $this->refresh_search();
                } elseif (!$this->search_set->incomplete) {
                    $this->search_set->filter(explode(',', $uids));
                }
            }

            // remove cached messages
            $this->clear_message_cache($folder, $all_mode ? null : explode(',', $uids));
        }

        return $deleted;
    }

    /**
     * Send IMAP expunge command and clear cache
     *
     * @param mixed  $uids        Message UIDs as array or comma-separated string, or '*'
     * @param string $folder      Folder name
     * @param bool   $clear_cache False if cache should not be cleared
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function expunge_message($uids, $folder = null, $clear_cache = true)
    {
        if ($uids && $this->get_capability('UIDPLUS')) {
            [$uids, $all_mode] = $this->parse_uids($uids);
        } else {
            $uids = null;
        }

        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        if (!$this->check_connection()) {
            return false;
        }

        // force folder selection and check if folder is writeable
        // to prevent a situation when CLOSE is executed on closed
        // or EXPUNGE on read-only folder
        $result = $this->conn->select($folder);
        if (!$result) {
            return false;
        }

        // CLOSE(+SELECT) should be faster than EXPUNGE
        if (empty($uids) || !empty($all_mode)) {
            $result = $this->conn->close();
        } else {
            $result = $this->conn->expunge($folder, $uids);
        }

        if ($result && $clear_cache) {
            $this->clear_message_cache($folder, (!empty($all_mode) || empty($uids)) ? null : explode(',', $uids));
            $this->clear_messagecount($folder);
        }

        return $result;
    }

    /**
     * Annotate a message.
     *
     * @param array  $annotation Message annotation key-value array
     * @param mixed  $uids       Message UIDs as array or comma-separated string, or '*'
     * @param string $folder     Folder name
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function annotate_message($annotation, $uids, $folder = null)
    {
        [$uids] = $this->parse_uids($uids);

        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        if (!$this->check_connection()) {
            return false;
        }

        unset($this->icache['message']);

        return $this->conn->storeMessageAnnotation($folder, $uids, $annotation);
    }

    /* --------------------------------
     *        folder management
     * --------------------------------*/

    /**
     * Public method for listing subscribed folders.
     *
     * @param string $root      Optional root folder
     * @param string $name      Optional name pattern
     * @param string $filter    Optional filter
     * @param string $rights    Optional ACL requirements
     * @param bool   $skip_sort Enable to return unsorted list (for better performance)
     *
     * @return array List of folders
     */
    #[Override]
    public function list_folders_subscribed($root = '', $name = '*', $filter = null, $rights = null, $skip_sort = false)
    {
        $cache_key = rcube_cache::key_name('mailboxes', [$root, $name, $filter, $rights]);

        // get cached folder list
        $a_mboxes = $this->get_cache($cache_key);
        if (is_array($a_mboxes)) {
            return $a_mboxes;
        }

        // Give plugins a chance to provide a list of folders
        $data = $this->plugins->exec_hook('storage_folders',
            ['root' => $root, 'name' => $name, 'filter' => $filter, 'mode' => 'LSUB']);

        if (isset($data['folders'])) {
            $a_mboxes = $data['folders'];
        } else {
            $a_mboxes = $this->list_folders_subscribed_direct($root, $name);
        }

        if (!is_array($a_mboxes)) {
            return [];
        }

        // filter folders list according to rights requirements
        if ($rights && $this->get_capability('ACL')) {
            $a_mboxes = $this->filter_rights($a_mboxes, $rights);
        }

        // INBOX should always be available
        if (in_array_nocase($root . $name, ['*', '%', 'INBOX', 'INBOX*'])
            && (!$filter || $filter == 'mail') && !in_array('INBOX', $a_mboxes)
        ) {
            array_unshift($a_mboxes, 'INBOX');
        }

        // sort folders (always sort for cache)
        if (!$skip_sort || $this->cache) {
            $a_mboxes = $this->sort_folder_list($a_mboxes);
        }

        // write folders list to cache
        $this->update_cache($cache_key, $a_mboxes);

        return $a_mboxes;
    }

    /**
     * Method for direct folders listing (LSUB)
     *
     * @param string $root Optional root folder
     * @param string $name Optional name pattern
     *
     * @return ?array List of subscribed folders
     *
     * @see rcube_imap::list_folders_subscribed()
     */
    public function list_folders_subscribed_direct($root = '', $name = '*')
    {
        if (!$this->check_connection()) {
            return null;
        }

        $config = rcube::get_instance()->config;
        $list_root = $root === '' && $this->list_root ? $this->list_root : $root;

        // Server supports LIST-EXTENDED, we can use selection options
        // #1486225: Some dovecot versions return wrong result using LIST-EXTENDED
        $list_extended = !$config->get('imap_force_lsub') && $this->get_capability('LIST-EXTENDED');

        if ($list_extended) {
            // This will also set folder options, LSUB doesn't do that
            $result = $this->conn->listMailboxes($list_root, $name, null, ['SUBSCRIBED']);
        } else {
            // retrieve list of folders from IMAP server using LSUB
            $result = $this->conn->listSubscribed($list_root, $name);
        }

        if (!is_array($result)) {
            return null;
        }

        // Add/Remove folders according to some configuration options
        $this->list_folders_filter($result, $root . $name, ($list_extended ? 'ext-' : '') . 'subscribed');

        // Save the last command state, so we can ignore errors on any following UNSUBSCRIBE calls
        $state = $this->save_conn_state();

        if ($list_extended) {
            // unsubscribe non-existent folders, remove from the list
            if ($name == '*' && !empty($this->conn->data['LIST'])) {
                foreach ($result as $idx => $folder) {
                    if (($opts = $this->conn->data['LIST'][$folder])
                        && in_array_nocase('\NonExistent', $opts)
                    ) {
                        $this->conn->unsubscribe($folder);
                        unset($result[$idx]);
                    }
                }
            }
        } else {
            // unsubscribe non-existent folders, remove them from the list
            if (!empty($result) && $name == '*') {
                $existing = $this->list_folders($root, $name);

                // Try to make sure the list of existing folders is not malformed,
                // we don't want to unsubscribe existing folders on error
                // @phpstan-ignore-next-line
                if (is_array($existing) && (!empty($root) || count($existing) > 1)) {
                    $nonexisting = array_diff($result, $existing);
                    $result = array_diff($result, $nonexisting);

                    foreach ($nonexisting as $folder) {
                        $this->conn->unsubscribe($folder);
                    }
                }
            }
        }

        $this->restore_conn_state($state);

        return $result;
    }

    /**
     * Get a list of all folders available on the server
     *
     * @param string $root      IMAP root dir
     * @param string $name      Optional name pattern
     * @param mixed  $filter    Optional filter
     * @param string $rights    Optional ACL requirements
     * @param bool   $skip_sort Enable to return unsorted list (for better performance)
     *
     * @return array Indexed array with folder names
     */
    #[Override]
    public function list_folders($root = '', $name = '*', $filter = null, $rights = null, $skip_sort = false)
    {
        $cache_key = rcube_cache::key_name('mailboxes.list', [$root, $name, $filter, $rights]);

        // get cached folder list
        $a_mboxes = $this->get_cache($cache_key);
        if (is_array($a_mboxes)) {
            return $a_mboxes;
        }

        // Give plugins a chance to provide a list of folders
        $data = $this->plugins->exec_hook('storage_folders',
            ['root' => $root, 'name' => $name, 'filter' => $filter, 'mode' => 'LIST']);

        if (isset($data['folders'])) {
            $a_mboxes = $data['folders'];
        } else {
            // retrieve list of folders from IMAP server
            $a_mboxes = $this->list_folders_direct($root, $name);
        }

        if (!is_array($a_mboxes)) {
            $a_mboxes = [];
        }

        // INBOX should always be available
        if (in_array_nocase($root . $name, ['*', '%', 'INBOX', 'INBOX*'])
            && (!$filter || $filter == 'mail') && !in_array('INBOX', $a_mboxes)
        ) {
            array_unshift($a_mboxes, 'INBOX');
        }

        // cache folder attributes
        if ($root == '' && $name == '*' && empty($filter) && !empty($this->conn->data)) {
            $this->update_cache('mailboxes.attributes', $this->conn->data['LIST']);
        }

        // filter folders list according to rights requirements
        if ($rights && $this->get_capability('ACL')) {
            $a_mboxes = $this->filter_rights($a_mboxes, $rights);
        }

        // filter folders and sort them
        if (!$skip_sort) {
            $a_mboxes = $this->sort_folder_list($a_mboxes);
        }

        // write folders list to cache
        $this->update_cache($cache_key, $a_mboxes);

        return $a_mboxes;
    }

    /**
     * Method for direct folders listing (LIST)
     *
     * @param string $root Optional root folder
     * @param string $name Optional name pattern
     *
     * @return ?array List of folders, Null on error
     *
     * @see rcube_imap::list_folders()
     */
    public function list_folders_direct($root = '', $name = '*')
    {
        if (!$this->check_connection()) {
            return null;
        }

        $list_root = $root === '' && $this->list_root ? $this->list_root : $root;

        $result = $this->conn->listMailboxes($list_root, $name);

        if (!is_array($result)) {
            return [];
        }

        // Add/Remove folders according to some configuration options
        $this->list_folders_filter($result, $root . $name);

        return $result;
    }

    /**
     * Apply configured filters on folders list
     */
    protected function list_folders_filter(&$result, $root, $update_type = null)
    {
        $config = rcube::get_instance()->config;

        // #1486796: some server configurations doesn't return folders in all namespaces
        if ($root === '*' && $config->get('imap_force_ns')) {
            $this->list_folders_update($result, $update_type);
        }

        // Remove hidden folders
        if ($config->get('imap_skip_hidden_folders')) {
            $result = array_filter($result, static function ($v) {
                return $v[0] != '.';
            });
        }

        // Remove folders in shared namespaces (if configured, see self::set_env())
        if ($root === '*' && !empty($this->list_excludes)) {
            $result = array_filter($result, function ($v) {
                foreach ($this->list_excludes as $prefix) {
                    if (str_starts_with($v, $prefix)) {
                        return false;
                    }
                }

                return true;
            });
        }
    }

    /**
     * Fix folders list by adding folders from other namespaces.
     * Needed on some servers e.g. Courier IMAP
     *
     * @param array  $result Reference to folders list
     * @param string $type   Listing type (ext-subscribed, subscribed or all)
     */
    protected function list_folders_update(&$result, $type = null)
    {
        $namespace = $this->get_namespace();
        $search = [];

        // build list of namespace prefixes
        foreach ((array) $namespace as $ns) {
            if (is_array($ns)) {
                foreach ($ns as $ns_data) {
                    if (strlen($ns_data[0])) {
                        $search[] = $ns_data[0];
                    }
                }
            }
        }

        if (!empty($search)) {
            // go through all folders detecting namespace usage
            foreach ($result as $folder) {
                foreach ($search as $idx => $prefix) {
                    if (str_starts_with($folder, $prefix)) {
                        unset($search[$idx]);
                    }
                }
                if (empty($search)) {
                    break;
                }
            }

            // get folders in hidden namespaces and add to the result
            foreach ($search as $prefix) {
                if ($type == 'ext-subscribed') {
                    $list = $this->conn->listMailboxes('', $prefix . '*', null, ['SUBSCRIBED']);
                } elseif ($type == 'subscribed') {
                    $list = $this->conn->listSubscribed('', $prefix . '*');
                } else {
                    $list = $this->conn->listMailboxes('', $prefix . '*');
                }

                if (!empty($list)) {
                    $result = array_merge($result, $list);
                }
            }
        }
    }

    /**
     * Filter the given list of folders according to access rights
     *
     * For performance reasons we assume user has full rights
     * on all personal folders.
     */
    protected function filter_rights($a_folders, $rights)
    {
        $regex = '/(' . $rights . ')/';

        foreach ($a_folders as $idx => $folder) {
            if ($this->folder_namespace($folder) == 'personal') {
                continue;
            }

            $myrights = implode('', (array) $this->my_rights($folder));

            if (!preg_match($regex, $myrights)) {
                unset($a_folders[$idx]);
            }
        }

        return $a_folders;
    }

    /**
     * Get mailbox quota information
     *
     * @param string $folder Folder name
     *
     * @return mixed Quota info or False if not supported
     */
    #[Override]
    public function get_quota($folder = null)
    {
        if ($this->get_capability('QUOTA') && $this->check_connection()) {
            return $this->conn->getQuota($folder);
        }

        return false;
    }

    /**
     * Get folder size (size of all messages in a folder)
     *
     * @param string $folder Folder name
     *
     * @return int|false Folder size in bytes, False on error
     */
    #[Override]
    public function folder_size($folder)
    {
        if (!strlen($folder)) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        if ($this->get_capability('STATUS=SIZE')) {
            $status = $this->conn->status($folder, ['SIZE']);
            if (is_array($status) && array_key_exists('SIZE', $status)) {
                return (int) $status['SIZE'];
            }
        }

        // On Cyrus we can use special folder annotation, which should be much faster
        if ($this->get_vendor() == 'cyrus') {
            $idx = '/shared/vendor/cmu/cyrus-imapd/size';
            $result = $this->get_metadata($folder, $idx, [], true);

            if (!empty($result) && isset($result[$folder][$idx]) && is_numeric($result[$folder][$idx])) {
                return (int) $result[$folder][$idx];
            }
        }

        // @TODO: could we try to use QUOTA here?
        $result = $this->conn->fetchHeaderIndex($folder, '1:*', 'SIZE', false);

        if (is_array($result)) {
            $result = array_sum($result);
        }

        return $result;
    }

    /**
     * Subscribe to a specific folder(s)
     *
     * @param array $folders Folder name(s)
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function subscribe($folders)
    {
        // let this common function do the main work
        return $this->change_subscription($folders, 'subscribe');
    }

    /**
     * Unsubscribe folder(s)
     *
     * @param array $folders Folder name(s)
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function unsubscribe($folders)
    {
        // let this common function do the main work
        return $this->change_subscription($folders, 'unsubscribe');
    }

    /**
     * Create a new folder on the server and register it in local cache
     *
     * @param string $folder    New folder name
     * @param bool   $subscribe True if the new folder should be subscribed
     * @param string $type      Optional folder type (junk, trash, drafts, sent, archive)
     * @param bool   $noselect  Make the folder a \NoSelect folder by adding hierarchy
     *                          separator at the end (useful for server that do not support
     *                          both folders and messages as folder children)
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function create_folder($folder, $subscribe = false, $type = null, $noselect = false)
    {
        if (!$this->check_connection()) {
            return false;
        }

        if ($noselect) {
            $folder .= $this->delimiter;
        }

        $result = $this->conn->createFolder($folder, $type ? ['\\' . ucfirst($type)] : null);

        // Folder creation may fail when specific special-use flag is not supported.
        // Try to create it anyway with no flag specified (#7147)
        if (!$result && $type) {
            $result = $this->conn->createFolder($folder);
        }

        // try to subscribe it
        if ($result) {
            // clear cache
            $this->clear_cache('mailboxes', true);

            if ($subscribe && !$noselect) {
                $this->subscribe($folder);
            }
        }

        return $result;
    }

    /**
     * Set a new name to an existing folder
     *
     * @param string $folder   Folder to rename
     * @param string $new_name New folder name
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function rename_folder($folder, $new_name)
    {
        if (!strlen($new_name)) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $delm = $this->get_hierarchy_delimiter();

        // get list of subscribed folders
        if ((strpos($folder, '%') === false) && (strpos($folder, '*') === false)) {
            $a_subscribed = $this->list_folders_subscribed($folder . $delm, '*');
            $subscribed = $this->folder_exists($folder, true);
        } else {
            $a_subscribed = $this->list_folders_subscribed();
            $subscribed = in_array($folder, $a_subscribed);
        }

        $result = $this->conn->renameFolder($folder, $new_name);

        if ($result) {
            // unsubscribe the old folder, subscribe the new one
            if ($subscribed) {
                $this->conn->unsubscribe($folder);
                $this->conn->subscribe($new_name);
            }

            // check if folder children are subscribed
            foreach ($a_subscribed as $c_subscribed) {
                if (str_starts_with($c_subscribed, $folder . $delm)) {
                    $this->conn->unsubscribe($c_subscribed);
                    $this->conn->subscribe(preg_replace('/^' . preg_quote($folder, '/') . '/',
                        $new_name, $c_subscribed));

                    // clear cache
                    $this->clear_message_cache($c_subscribed);
                }
            }

            // clear cache
            $this->clear_message_cache($folder);
            $this->clear_cache('mailboxes', true);
        }

        return $result;
    }

    /**
     * Remove folder (with subfolders) from the server
     *
     * @param string $folder Folder name
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function delete_folder($folder)
    {
        if (!$this->check_connection()) {
            return false;
        }

        $delm = $this->get_hierarchy_delimiter();

        // get list of sub-folders or all folders
        // if folder name contains special characters
        $path = strpos($folder, '*') === false && strpos($folder, '%') === false ? ($folder . $delm) : '';
        $sub_mboxes = $this->list_folders($path, '*');

        // According to RFC3501 deleting a \Noselect folder
        // with subfolders may fail. To workaround this we delete
        // subfolders first (in reverse order) (#5466)
        if (!empty($sub_mboxes)) {
            foreach (array_reverse($sub_mboxes) as $mbox) {
                if (str_starts_with($mbox, $folder . $delm)) {
                    if ($this->conn->deleteFolder($mbox)) {
                        $this->conn->unsubscribe($mbox);
                        $this->clear_message_cache($mbox);
                    }
                }
            }
        }

        // delete the folder
        if ($result = $this->conn->deleteFolder($folder)) {
            // and unsubscribe it
            $this->conn->unsubscribe($folder);
            $this->clear_message_cache($folder);
        }

        $this->clear_cache('mailboxes', true);

        return $result;
    }

    /**
     * Detect special folder associations stored in storage backend
     */
    #[Override]
    public function get_special_folders($forced = false)
    {
        $result = parent::get_special_folders();
        $rcube = rcube::get_instance();

        // Lock SPECIAL-USE after user preferences change (#4782)
        if ($rcube->config->get('lock_special_folders')) {
            return $result;
        }

        if (isset($this->icache['special-use'])) {
            return array_merge($result, $this->icache['special-use']);
        }

        if (!$forced || !$this->get_capability('SPECIAL-USE')) {
            return $result;
        }

        if (!$this->check_connection()) {
            return $result;
        }

        $types = array_map(static function ($value) {
            return '\\' . ucfirst($value);
        }, rcube_storage::$folder_types);
        $special = [];

        // request \Subscribed flag in LIST response as performance improvement for folder_exists()
        $folders = $this->conn->listMailboxes('', '*', ['SUBSCRIBED'], ['SPECIAL-USE']);

        if (!empty($folders)) {
            foreach ($folders as $idx => $folder) {
                if (is_array($folder)) {
                    $folder = $idx;
                }
                if (!empty($this->conn->data['LIST'][$folder])) {
                    $flags = $this->conn->data['LIST'][$folder];
                    foreach ($types as $type) {
                        if (in_array($type, $flags)) {
                            $type = strtolower(substr($type, 1));

                            // Ignore all but first personal special folder per type (#9781)
                            if (isset($special[$type]) && $this->folder_namespace($special[$type]) == 'personal') {
                                continue;
                            }

                            $special[$type] = $folder;
                        }
                    }
                }
            }
        }

        $this->icache['special-use'] = $special;
        unset($this->icache['special-folders']);

        return array_merge($result, $special);
    }

    /**
     * Set special folder associations stored in storage backend
     */
    #[Override]
    public function set_special_folders($specials)
    {
        if (!$this->get_capability('SPECIAL-USE') || !$this->get_capability('METADATA')) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $folders = $this->get_special_folders(true);
        $old = isset($this->icache['special-use']) ? (array) $this->icache['special-use'] : [];

        foreach ($specials as $type => $folder) {
            if (in_array($type, rcube_storage::$folder_types)) {
                $old_folder = $old[$type] ?? null;
                if ($old_folder !== $folder) {
                    // unset old-folder metadata
                    if ($old_folder !== null) {
                        $this->delete_metadata($old_folder, ['/private/specialuse']);
                    }
                    // set new folder metadata
                    if ($folder) {
                        $this->set_metadata($folder, ['/private/specialuse' => '\\' . ucfirst($type)]);
                    }
                }
            }
        }

        $this->icache['special-use'] = $specials;
        unset($this->icache['special-folders']);

        return true;
    }

    /**
     * Checks if folder exists and is subscribed
     *
     * @param string $folder       Folder name
     * @param bool   $subscription Enable subscription checking
     *
     * @return bool True or False
     */
    #[Override]
    public function folder_exists($folder, $subscription = false)
    {
        if ($folder == 'INBOX') {
            return true;
        }

        $key = $subscription ? 'subscribed' : 'existing';

        if (!empty($this->icache[$key]) && in_array($folder, (array) $this->icache[$key])) {
            return true;
        }

        if (!$this->check_connection()) {
            return false;
        }

        if ($subscription) {
            // It's possible we already called LIST command, check LIST data
            if (!empty($this->conn->data['LIST']) && !empty($this->conn->data['LIST'][$folder])
                && in_array_nocase('\Subscribed', $this->conn->data['LIST'][$folder])
            ) {
                $a_folders = [$folder];
            } else {
                $a_folders = $this->conn->listSubscribed('', $folder);
            }
        } else {
            // It's possible we already called LIST command, check LIST data
            if (!empty($this->conn->data['LIST']) && isset($this->conn->data['LIST'][$folder])) {
                $a_folders = [$folder];
            } else {
                $a_folders = $this->conn->listMailboxes('', $folder);
            }
        }

        if (is_array($a_folders) && in_array($folder, $a_folders)) {
            $this->icache[$key][] = $folder;
            return true;
        }

        return false;
    }

    /**
     * Returns the namespace where the folder is in
     *
     * @param string $folder Folder name
     *
     * @return string One of 'personal', 'other' or 'shared'
     */
    #[Override]
    public function folder_namespace($folder)
    {
        if ($folder == 'INBOX') {
            return 'personal';
        }

        foreach ($this->namespace as $type => $namespace) {
            if (is_array($namespace)) {
                foreach ($namespace as $ns) {
                    if ($len = strlen($ns[0])) {
                        if (($len > 1 && $folder == substr($ns[0], 0, -1))
                            || str_starts_with($folder, $ns[0])
                        ) {
                            return $type;
                        }
                    }
                }
            }
        }

        return 'personal';
    }

    /**
     * Modify folder name according to personal namespace prefix.
     * For output it removes prefix of the personal namespace if it's possible.
     * For input it adds the prefix. Use it before creating a folder in root
     * of the folders tree.
     *
     * @param string $folder Folder name
     * @param string $mode   Mode name (out/in)
     *
     * @return string Folder name
     */
    #[Override]
    public function mod_folder($folder, $mode = 'out')
    {
        $prefix = $this->namespace['prefix_' . $mode] ?? null;

        if ($prefix === null || $prefix === ''
            || !($prefix_len = strlen($prefix)) || !strlen($folder)
        ) {
            return $folder;
        }

        // remove prefix for output
        if ($mode == 'out') {
            if (substr($folder, 0, $prefix_len) === $prefix) {
                return substr($folder, $prefix_len);
            }

            return $folder;
        }

        // add prefix for input (e.g. folder creation)
        return $prefix . $folder;
    }

    /**
     * Gets folder attributes from LIST response, e.g. \Noselect, \Noinferiors
     *
     * @param string $folder Folder name
     * @param bool   $force  Set to True if attributes should be refreshed
     *
     * @return array Options list
     */
    #[Override]
    public function folder_attributes($folder, $force = false)
    {
        // get attributes directly from LIST command
        if (!empty($this->conn->data['LIST'])
            && isset($this->conn->data['LIST'][$folder])
            && is_array($this->conn->data['LIST'][$folder])
        ) {
            $opts = $this->conn->data['LIST'][$folder];
        }
        // get cached folder attributes
        elseif (!$force) {
            $opts = $this->get_cache('mailboxes.attributes');
            if ($opts && isset($opts[$folder])) {
                $opts = $opts[$folder];
            }
        }

        if (!isset($opts) || !is_array($opts)) {
            if (!$this->check_connection()) {
                return [];
            }

            $this->conn->listMailboxes('', $folder);

            if (isset($this->conn->data['LIST'][$folder])) {
                $opts = $this->conn->data['LIST'][$folder];
            }
        }

        return isset($opts) && is_array($opts) ? $opts : [];
    }

    /**
     * Gets connection (and current folder) data: UIDVALIDITY, EXISTS, RECENT,
     * PERMANENTFLAGS, UIDNEXT, UNSEEN
     *
     * @param string $folder Folder name
     *
     * @return array Folder properties
     */
    #[Override]
    public function folder_data($folder)
    {
        if (!strlen((string) $folder)) {
            $folder = $this->folder !== null ? $this->folder : 'INBOX';
        }

        if ($this->conn->selected != $folder) {
            if (!$this->check_connection()) {
                return [];
            }

            if ($this->conn->select($folder)) {
                $this->folder = $folder;
            } else {
                return [];
            }
        }

        $data = $this->conn->data;

        // add (E)SEARCH result for ALL UNDELETED query
        if (!empty($this->icache['undeleted_idx'])
            && $this->icache['undeleted_idx']->get_parameters('MAILBOX') == $folder
        ) {
            $data['UNDELETED'] = $this->icache['undeleted_idx'];
        }

        // dovecot does not return HIGHESTMODSEQ until requested, we use it though in our caching system
        // calling STATUS is needed only once, after first use mod-seq db will be maintained
        if (!isset($data['HIGHESTMODSEQ']) && empty($data['NOMODSEQ'])
            && ($this->get_capability('QRESYNC') || $this->get_capability('CONDSTORE'))
        ) {
            if ($add_data = $this->conn->status($folder, ['HIGHESTMODSEQ'])) {
                $data = array_merge($data, $add_data);
            }
        }

        return $data;
    }

    /**
     * Returns extended information about the folder
     *
     * @param string $folder Folder name
     *
     * @return array Data
     */
    #[Override]
    public function folder_info($folder)
    {
        if (!empty($this->icache['options']) && $this->icache['options']['name'] == $folder) {
            return $this->icache['options'];
        }

        // get cached metadata
        $cache_key = rcube_cache::key_name('mailboxes.folder-info', [$folder]);
        $cached = $this->get_cache($cache_key);

        if (is_array($cached)) {
            return $cached;
        }

        $acl = $this->get_capability('ACL');
        $namespace = $this->get_namespace();
        $options = ['is_root' => false];

        // check if the folder is a namespace prefix
        if (!empty($namespace)) {
            $mbox = $folder . $this->delimiter;
            foreach ($namespace as $ns) {
                if (!empty($ns)) {
                    foreach ($ns as $item) {
                        if ($item[0] === $mbox) {
                            $options['is_root'] = true;
                            break 2;
                        }
                    }
                }
            }
        }
        // check if the folder is other user virtual-root
        if ($options['is_root'] && !empty($namespace) && !empty($namespace['other'])) {
            $parts = explode($this->delimiter, $folder);
            if (count($parts) == 2) {
                $mbox = $parts[0] . $this->delimiter;
                foreach ($namespace['other'] as $item) {
                    if ($item[0] === $mbox) {
                        $options['is_root'] = true;
                        break;
                    }
                }
            }
        }

        $options['name'] = $folder;
        $options['attributes'] = $this->folder_attributes($folder, true);
        $options['namespace'] = $this->folder_namespace($folder);
        $options['special'] = $this->is_special_folder($folder);
        $options['noselect'] = false;

        // Set 'noselect' flag
        foreach ($options['attributes'] as $attrib) {
            $attrib = strtolower($attrib);
            if ($attrib == '\noselect' || $attrib == '\nonexistent') {
                $options['noselect'] = true;
            }
        }

        // Get folder rights (MYRIGHTS)
        if ($acl && ($rights = $this->my_rights($folder))) {
            $options['rights'] = $rights;
        }

        // Set 'norename' flag
        if (!empty($options['rights'])) {
            $rfc_4314 = is_array($this->get_capability('RIGHTS'));
            $options['norename'] = ($rfc_4314 && !in_array('x', $options['rights']))
                                || (!$rfc_4314 && !in_array('d', $options['rights']));

            if (!$options['noselect']) {
                $options['noselect'] = !in_array('r', $options['rights']);
            }
        } else {
            $options['norename'] = $options['is_root'] || $options['namespace'] != 'personal';
        }

        // update caches
        $this->icache['options'] = $options;
        $this->update_cache($cache_key, $options);

        return $options;
    }

    /**
     * Synchronizes messages cache.
     *
     * @param string $folder Folder name
     */
    #[Override]
    public function folder_sync($folder)
    {
        if ($mcache = $this->get_mcache_engine()) {
            $mcache->synchronize($folder);
        }
    }

    /**
     * Check if the folder name is valid
     *
     * @param string $folder Folder name (UTF-8)
     * @param string &$char  First forbidden character found
     *
     * @return bool True if the name is valid, False otherwise
     */
    #[Override]
    public function folder_validate($folder, &$char = null)
    {
        if (parent::folder_validate($folder, $char)) {
            $vendor = $this->get_vendor();
            $regexp = '\x00-\x1F\x7F%*';

            if ($vendor == 'cyrus') {
                // List based on testing Kolab's Cyrus-IMAP 2.5
                $regexp .= '!`@(){}|\?<;"';
            }

            if (!preg_match("/[{$regexp}]/", $folder, $m)) {
                return true;
            }

            $char = $m[0];
        }

        return false;
    }

    /**
     * Get message header names for rcube_imap_generic::fetchHeader(s)
     *
     * @return array List of header names
     */
    protected function get_fetch_headers()
    {
        if (!empty($this->options['fetch_headers'])) {
            $headers = explode(' ', $this->options['fetch_headers']);
        } else {
            $headers = [];
        }

        if ($this->messages_caching || !empty($this->options['all_headers'])) {
            $headers = array_merge($headers, $this->all_headers);
        }

        return $headers;
    }

    /**
     * Get additional FETCH items for rcube_imap_generic::fetchHeader(s)
     *
     * @return array List of items
     */
    protected function get_fetch_items()
    {
        return $this->options['fetch_items'] ?? [];
    }

    /* -----------------------------------------
     *   ACL and METADATA/ANNOTATEMORE methods
     * ----------------------------------------*/

    /**
     * Changes the ACL on the specified folder (SETACL)
     *
     * @param string $folder Folder name
     * @param string $user   User name
     * @param string $acl    ACL string
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function set_acl($folder, $user, $acl)
    {
        if (!$this->get_capability('ACL')) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $this->clear_cache(rcube_cache::key_name('mailboxes.folder-info', [$folder]));

        return $this->conn->setACL($folder, $user, $acl);
    }

    /**
     * Removes any <identifier,rights> pair for the
     * specified user from the ACL for the specified
     * folder (DELETEACL)
     *
     * @param string $folder Folder name
     * @param string $user   User name
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function delete_acl($folder, $user)
    {
        if (!$this->get_capability('ACL')) {
            return false;
        }

        if (!$this->check_connection()) {
            return false;
        }

        return $this->conn->deleteACL($folder, $user);
    }

    /**
     * Returns the access control list for folder (GETACL)
     *
     * @param string $folder Folder name
     *
     * @return array|null User-rights array on success, NULL on error
     */
    #[Override]
    public function get_acl($folder)
    {
        if (!$this->get_capability('ACL')) {
            return null;
        }

        if (!$this->check_connection()) {
            return null;
        }

        return $this->conn->getACL($folder);
    }

    /**
     * Returns information about what rights can be granted to the
     * user (identifier) in the ACL for the folder (LISTRIGHTS)
     *
     * @param string $folder Folder name
     * @param string $user   User name
     *
     * @return array|null List of user rights
     */
    #[Override]
    public function list_rights($folder, $user)
    {
        if (!$this->get_capability('ACL')) {
            return null;
        }

        if (!$this->check_connection()) {
            return null;
        }

        return $this->conn->listRights($folder, $user);
    }

    /**
     * Returns the set of rights that the current user has to
     * folder (MYRIGHTS)
     *
     * @param string $folder Folder name
     *
     * @return array|null MYRIGHTS response on success, NULL on error
     */
    #[Override]
    public function my_rights($folder)
    {
        if (!$this->get_capability('ACL')) {
            return null;
        }

        if (!$this->check_connection()) {
            return null;
        }

        return $this->conn->myRights($folder);
    }

    /**
     * Sets IMAP metadata/annotations (SETMETADATA/SETANNOTATION)
     *
     * @param ?string $folder  Folder name (empty for server metadata)
     * @param array   $entries Entry-value array (use NULL value as NIL)
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function set_metadata($folder, $entries)
    {
        if (!$this->check_connection()) {
            return false;
        }

        $this->clear_cache('mailboxes.metadata.', true);

        if ($this->get_capability('METADATA')
            || (!is_string($folder) || !strlen($folder) && $this->get_capability('METADATA-SERVER'))
        ) {
            return $this->conn->setMetadata($folder, $entries);
        }

        if ($this->get_capability('ANNOTATEMORE') || $this->get_capability('ANNOTATEMORE2')) {
            foreach ((array) $entries as $entry => $value) {
                [$ent, $attr] = $this->md2annotate($entry);
                $entries[$entry] = [$ent, $attr, $value];
            }

            return $this->conn->setAnnotation($folder, $entries);
        }

        return false;
    }

    /**
     * Unsets IMAP metadata/annotations (SETMETADATA/SETANNOTATION)
     *
     * @param ?string $folder  Folder name (empty for server metadata)
     * @param array   $entries Entry names array
     *
     * @return bool True on success, False on failure
     */
    #[Override]
    public function delete_metadata($folder, $entries)
    {
        if (!$this->check_connection()) {
            return false;
        }

        $this->clear_cache('mailboxes.metadata.', true);

        if ($this->get_capability('METADATA')
            || (!is_string($folder) || !strlen($folder) && $this->get_capability('METADATA-SERVER'))
        ) {
            return $this->conn->deleteMetadata($folder, $entries);
        }

        if ($this->get_capability('ANNOTATEMORE') || $this->get_capability('ANNOTATEMORE2')) {
            foreach ((array) $entries as $idx => $entry) {
                [$ent, $attr] = $this->md2annotate($entry);
                $entries[$idx] = [$ent, $attr, null];
            }

            return $this->conn->setAnnotation($folder, $entries);
        }

        return false;
    }

    /**
     * Returns IMAP metadata/annotations (GETMETADATA/GETANNOTATION)
     *
     * @param ?string $folder  Folder name (empty for server metadata)
     * @param array   $entries Entries
     * @param array   $options Command options (with MAXSIZE and DEPTH keys)
     * @param bool    $force   Disables cache use
     *
     * @return array|null Metadata entry-value hash array on success, NULL on error
     */
    #[Override]
    public function get_metadata($folder, $entries, $options = [], $force = false)
    {
        $entries = (array) $entries;

        if (!$force) {
            $cache_key = rcube_cache::key_name('mailboxes.metadata', [$folder, $options, $entries]);

            // get cached data
            $cached_data = $this->get_cache($cache_key);

            if (is_array($cached_data)) {
                return $cached_data;
            }
        }

        if (!$this->check_connection()) {
            return null;
        }

        if ($this->get_capability('METADATA')
            || (!is_string($folder) || !strlen($folder) && $this->get_capability('METADATA-SERVER'))
        ) {
            $res = $this->conn->getMetadata($folder, $entries, $options);
        } elseif ($this->get_capability('ANNOTATEMORE') || $this->get_capability('ANNOTATEMORE2')) {
            $queries = [];
            $res = [];

            // Convert entry names
            foreach ($entries as $entry) {
                [$ent, $attr] = $this->md2annotate($entry);
                $queries[$attr][] = $ent;
            }

            // @TODO: Honor MAXSIZE and DEPTH options
            foreach ($queries as $attrib => $entry) {
                $result = $this->conn->getAnnotation($folder, $entry, $attrib);

                // an error, invalidate any previous getAnnotation() results
                if (!is_array($result)) {
                    return null;
                }

                foreach ($result as $fldr => $data) {
                    $res[$fldr] = array_merge((array) $res[$fldr], $data);
                }
            }
        }

        if (isset($res)) {
            if (!$force && !empty($cache_key)) {
                $this->update_cache($cache_key, $res);
            }

            return $res;
        }

        return null;
    }

    /**
     * Converts the METADATA extension entry name into the correct
     * entry-attrib names for older ANNOTATEMORE version.
     *
     * @param string $entry Entry name
     *
     * @return array|null Entry-attribute list, NULL if not supported (?)
     */
    protected function md2annotate($entry)
    {
        if (substr($entry, 0, 7) == '/shared') {
            return [substr($entry, 7), 'value.shared'];
        }

        if (substr($entry, 0, 8) == '/private') {
            return [substr($entry, 8), 'value.priv'];
        }

        // @TODO: log error
        return null;
    }

    /* --------------------------------
     *   internal caching methods
     * --------------------------------*/

    /**
     * Enable or disable indexes caching
     *
     * @param string $type Cache type (@see rcube::get_cache)
     */
    #[Override]
    public function set_caching($type)
    {
        if ($type) {
            $this->caching = $type;
        } else {
            if ($this->cache) {
                $this->cache->close();
            }
            $this->cache = null;
            $this->caching = false;
        }
    }

    /**
     * Getter for IMAP cache object
     *
     * @return rcube_cache|null
     */
    protected function get_cache_engine()
    {
        if ($this->caching && !$this->cache) {
            $rcube = rcube::get_instance();
            $ttl = $rcube->config->get('imap_cache_ttl', '10d');
            $this->cache = $rcube->get_cache('IMAP', $this->caching, $ttl);
        }

        return $this->cache;
    }

    /**
     * Returns cached value
     *
     * @param string $key Cache key
     *
     * @return mixed
     */
    #[Override]
    public function get_cache($key)
    {
        if ($cache = $this->get_cache_engine()) {
            return $cache->get($key);
        }

        return null;
    }

    /**
     * Update cache
     *
     * @param string $key  Cache key
     * @param mixed  $data Data
     */
    public function update_cache($key, $data)
    {
        if ($cache = $this->get_cache_engine()) {
            $cache->set($key, $data);
        }
    }

    /**
     * Clears the cache.
     *
     * @param string $key         Cache key name or pattern
     * @param bool   $prefix_mode Enable it to clear all keys starting
     *                            with prefix specified in $key
     */
    #[Override]
    public function clear_cache($key = null, $prefix_mode = false)
    {
        if ($cache = $this->get_cache_engine()) {
            $cache->remove($key, $prefix_mode);
        }
    }

    /* --------------------------------
     *   message caching methods
     * --------------------------------*/

    /**
     * Enable or disable messages caching
     *
     * @param bool $set  Flag
     * @param int  $mode Cache mode
     */
    #[Override]
    public function set_messages_caching($set, $mode = null)
    {
        if ($set) {
            $this->messages_caching = true;

            if ($mode && ($cache = $this->get_mcache_engine())) {
                $cache->set_mode($mode);
            }
        } else {
            if ($this->mcache) {
                $this->mcache->close();
            }
            $this->mcache = null;
            $this->messages_caching = false;
        }
    }

    /**
     * Getter for messages cache object
     */
    protected function get_mcache_engine()
    {
        if ($this->messages_caching && !$this->mcache) {
            $rcube = rcube::get_instance();
            $dbh = $rcube->get_dbh();
            if ($userid = $rcube->get_user_id()) {
                $ttl = $rcube->config->get('messages_cache_ttl', '10d');
                $threshold = $rcube->config->get('messages_cache_threshold', 50);
                $this->mcache = new rcube_imap_cache(
                    $dbh, $this, $userid, $this->options['skip_deleted'], $ttl, $threshold);
            }
        }

        return $this->mcache;
    }

    /**
     * Clears the messages cache.
     *
     * @param string $folder Folder name
     * @param array  $uids   Optional message UIDs to remove from cache
     */
    protected function clear_message_cache($folder = null, $uids = null)
    {
        if ($mcache = $this->get_mcache_engine()) {
            $mcache->clear($folder, $uids);
        }
    }

    /**
     * Delete outdated cache entries
     */
    #[Override]
    public function cache_gc()
    {
        rcube_imap_cache::gc();
    }

    /* --------------------------------
     *         protected methods
     * --------------------------------*/

    /**
     * Determines if server supports dual use folders (those can
     * contain both sub-folders and messages).
     *
     * @return bool
     */
    protected function detect_dual_use_folders()
    {
        $val = rcube::get_instance()->config->get('imap_dual_use_folders');
        if ($val !== null) {
            return (bool) $val;
        }

        if (!$this->check_connection()) {
            return false;
        }

        $folder = str_replace('.', '', 'foldertest' . microtime(true));
        $folder = $this->mod_folder($folder, 'in');
        $subfolder = $folder . $this->delimiter . 'foldertest';

        if ($this->conn->createFolder($folder)) {
            if ($created = $this->conn->createFolder($subfolder)) {
                $this->conn->deleteFolder($subfolder);
            }

            $this->conn->deleteFolder($folder);

            return $created;
        }

        return false;
    }

    /**
     * Validate the given input and save to local properties
     *
     * @param string $sort_field Sort column
     * @param string $sort_order Sort order
     */
    protected function set_sort_order($sort_field, $sort_order)
    {
        if ($sort_field != null) {
            $this->sort_field = asciiwords($sort_field);
        }
        if ($sort_order != null) {
            $this->sort_order = strtoupper($sort_order) == 'DESC' ? 'DESC' : 'ASC';
        }
    }

    /**
     * Sort folders in alphabetical order. Optionally put special folders
     * first and other-users/shared namespaces last.
     *
     * @param array $a_folders    Folders list
     * @param bool  $skip_special Skip special folders handling
     *
     * @return array Sorted list
     */
    public function sort_folder_list($a_folders, $skip_special = false)
    {
        $folders = [];

        // convert names to UTF-8
        foreach ($a_folders as $folder) {
            // for better performance skip encoding conversion
            // if the string does not look like UTF7-IMAP
            $folders[$folder] = strpos($folder, '&') === false ? $folder : rcube_charset::convert($folder, 'UTF7-IMAP');
        }

        // sort folders
        // asort($folders, SORT_LOCALE_STRING) is not properly sorting case sensitive names
        uasort($folders, [$this, 'sort_folder_comparator']);

        $folders = array_keys($folders);

        if ($skip_special || empty($folders)) {
            return $folders;
        }

        // Force the type of folder name variable (#1485527)
        $folders = array_map('strval', $folders);

        $count = count($folders);
        $special = [];
        $inbox = [];
        $shared = [];
        $match_function = static function ($idx, $search, $prefix, &$list) use (&$folders) {
            $folder = $folders[$idx];
            if ($folder !== null && ($folder === $search || str_starts_with($folder, $prefix))) {
                $folders[$idx] = null;
                $list[] = $folder;
            }
        };

        // Put special folders first
        foreach ($this->get_special_folders() as $special_folder) {
            for ($i = 0; $i < $count; $i++) {
                $match_function($i, $special_folder, $special_folder . $this->delimiter, $special);
            }
        }

        // Note: Special folders might be subfolders of Inbox, that's why we don't do Inbox first
        for ($i = 0; $i < $count; $i++) {
            $match_function($i, 'INBOX', 'INBOX' . $this->delimiter, $inbox);
        }

        // Put other-user/shared namespaces at the end
        foreach (['other', 'shared'] as $ns_name) {
            if ($ns = $this->get_namespace($ns_name)) {
                foreach ($ns as $root) {
                    if (isset($root[0]) && strlen($root[0])) {
                        $search = rtrim($root[0], $root[1]);
                        for ($i = 0; $i < $count; $i++) {
                            $match_function($i, $search, $root[0], $shared);
                        }
                    }
                }
            }
        }

        // If special folders are INBOX's subfolders we need to place them after INBOX, otherwise
        // they won't be displayed in proper order in UI after we remove the namespace prefix (#9452)
        // FIXME: All this looks complicated and fragile, other options?
        if (!empty($special) && !empty($inbox) && $inbox[0] == 'INBOX'
            && !empty($this->namespace['personal'])
            && count($this->namespace['personal']) == 1
        ) {
            $insert = [];
            foreach ($special as $idx => $spec_folder) {
                if (str_starts_with($spec_folder, 'INBOX' . $this->delimiter)) {
                    $insert[] = $spec_folder;
                    unset($special[$idx]);
                }
            }
            if (!empty($insert)) {
                array_shift($inbox);
                $inbox = array_merge(['INBOX'], $insert, $inbox);
            }
        }

        return array_merge(
            $inbox, // INBOX and its subfolders
            $special, // special folders and their subfolders
            array_filter($folders, static function ($v) { return $v !== null; }), // all other personal folders
            $shared // shared/other users namespace folders
        );
    }

    /**
     * Callback for uasort() that implements correct
     * locale-aware case-sensitive sorting
     */
    protected function sort_folder_comparator($str1, $str2)
    {
        if ($this->sort_folder_collator === null) {
            $this->sort_folder_collator = false;

            // strcoll() does not work with UTF8 locale on Windows,
            // use Collator from the intl extension
            if (stripos(\PHP_OS, 'win') === 0 && function_exists('collator_compare')) {
                $locale = ($this->options['language'] ?? null) ?: 'en_US';
                $this->sort_folder_collator = collator_create($locale) ?: false;
            }
        }

        $path1 = explode($this->delimiter, $str1);
        $path2 = explode($this->delimiter, $str2);

        $len = max(count($path1), count($path2));

        for ($idx = 0; $idx < $len; $idx++) {
            $folder1 = $path1[$idx] ?? '';
            $folder2 = $path2[$idx] ?? '';

            if ($folder1 === $folder2) {
                continue;
            }

            if ($this->sort_folder_collator) {
                return collator_compare($this->sort_folder_collator, $folder1, $folder2);
            }

            return strcoll($folder1, $folder2);
        }
    }

    /**
     * Find UID of the specified message sequence ID
     *
     * @param int    $id     Message (sequence) ID
     * @param string $folder Folder name
     *
     * @return int|null Message UID if found
     */
    public function id2uid($id, $folder = null)
    {
        if (!is_string($folder) || !strlen($folder)) {
            $folder = $this->folder;
        }

        if (!$this->check_connection()) {
            return null;
        }

        return $this->conn->ID2UID($folder, $id);
    }

    /**
     * Subscribe/unsubscribe a list of folders and update local cache
     */
    protected function change_subscription($folders, $mode)
    {
        $updated = 0;
        $folders = (array) $folders;

        if (!empty($folders)) {
            if (!$this->check_connection()) {
                return false;
            }

            foreach ($folders as $folder) {
                $updated += (int) $this->conn->{$mode}($folder);
            }
        }

        // clear cached folders list(s)
        if ($updated) {
            $this->clear_cache('mailboxes', true);
        }

        return $updated == count($folders);
    }

    /**
     * Increase/decrease messagecount for a specific folder
     */
    protected function set_messagecount($folder, $mode, $increment)
    {
        if (!is_numeric($increment)) {
            return false;
        }

        $mode = strtoupper($mode);
        $a_folder_cache = $this->get_cache('messagecount');

        if (
            !isset($a_folder_cache[$folder])
            || !is_array($a_folder_cache[$folder])
            || !isset($a_folder_cache[$folder][$mode])
        ) {
            return false;
        }

        // add incremental value to messagecount
        $a_folder_cache[$folder][$mode] += $increment;

        // there's something wrong, delete from cache
        if ($a_folder_cache[$folder][$mode] < 0) {
            unset($a_folder_cache[$folder][$mode]);
        }

        // write back to cache
        $this->update_cache('messagecount', $a_folder_cache);

        return true;
    }

    /**
     * Remove messagecount of a specific folder from cache
     */
    protected function clear_messagecount($folder, $mode = [])
    {
        $a_folder_cache = $this->get_cache('messagecount');

        if (isset($a_folder_cache[$folder]) && is_array($a_folder_cache[$folder])) {
            if (!empty($mode)) {
                foreach ((array) $mode as $key) {
                    unset($a_folder_cache[$folder][$key]);
                }
            } else {
                unset($a_folder_cache[$folder]);
            }

            $this->update_cache('messagecount', $a_folder_cache);
        }
    }

    /**
     * Converts date string/object into IMAP date/time format
     */
    protected function date_format($date)
    {
        if (empty($date)) {
            return null;
        }

        if (!is_object($date) || !is_a($date, 'DateTime')) {
            try {
                $timestamp = rcube_utils::strtotime($date);
                $date = new DateTime('@' . $timestamp);
            } catch (Exception $e) {
                return null;
            }
        }

        return $date->format('d-M-Y H:i:s O');
    }

    /**
     * Remember state of the IMAP connection (last IMAP command).
     * Use e.g. if you want to execute more commands and ignore results of these.
     *
     * @return array Connection state
     */
    protected function save_conn_state()
    {
        return [
            $this->conn->error,
            $this->conn->errornum,
            $this->conn->resultcode,
        ];
    }

    /**
     * Restore saved connection state.
     *
     * @param array $state Connection result
     */
    protected function restore_conn_state($state)
    {
        [$this->conn->error, $this->conn->errornum, $this->conn->resultcode] = $state;
    }

    /**
     * This is our own debug handler for the IMAP connection
     */
    public function debug_handler($imap, $message)
    {
        rcube::write_log('imap', $message);
    }
}
