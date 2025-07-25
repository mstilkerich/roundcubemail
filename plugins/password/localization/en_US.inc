<?php

/*
 +-----------------------------------------------------------------------+
 | Localization file of the Roundcube Webmail Password plugin            |
 |                                                                       |
 | Copyright (C) The Roundcube Dev Team                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 +-----------------------------------------------------------------------+
*/

$labels['changepasswd']  = 'Change password';
$labels['curpasswd']  = 'Current Password:';
$labels['newpasswd']  = 'New Password:';
$labels['confpasswd']  = 'Confirm New Password:';

$messages['nopassword'] = 'Please input new password.';
$messages['nocurpassword'] = 'Please input current password.';
$messages['passwordincorrect'] = 'Current password incorrect.';
$messages['passwordinconsistency'] = 'Passwords do not match, please try again.';
$messages['crypterror'] = 'Could not save new password. Encryption function missing.';
$messages['connecterror'] = 'Could not save new password. Connection error.';
$messages['internalerror'] = 'Could not save new password.';
$messages['passwordshort'] = 'Password must be at least $length characters long.';
$messages['passwordweak'] = 'Password must include at least one number and one punctuation character.';
$messages['passwordtooweak'] = 'Password too weak.';
$messages['passwordnoseq'] = 'Password should not be a sequence like 123456 or QWERTY.';
$messages['passwordnocommon'] = 'Password should not be a common word or name.';
$messages['passwordforbidden'] = 'Password contains forbidden characters.';
$messages['firstloginchange'] = 'This is your first login. Please change your password.';
$messages['disablednotice'] = 'The system is currently under maintenance and password change is not possible at the moment. Everything should be back to normal soon. We apologize for any inconvenience.';
$messages['passwinhistory'] = 'This password has already been used previously.';
$messages['samepasswd'] = 'New password have to be different from the old one.';
$messages['passwdexpirewarning'] = 'Warning! Your password will expire soon, change it before $expirationdatetime.';
$messages['passwdexpired'] = 'Your password has expired, you have to change it now!';
$messages['passwdconstraintviolation'] = 'Password constraint violation. Password probably too weak.';
$messages['pwned_mustnotbedisclosed'] = 'Password must not be <a href="$href" target="_blank">commonly known</a>.';
$messages['pwned_isdisclosed'] = 'This password is commonly known.';
$messages['pwned_fetcherror'] = 'Failed to verify the password strength.';
