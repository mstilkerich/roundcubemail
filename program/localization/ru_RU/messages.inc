<?php

/*
 +-----------------------------------------------------------------------+
 | Localization file of the Roundcube Webmail client                     |
 |                                                                       |
 | Copyright (C) The Roundcube Dev Team                                  |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 +-----------------------------------------------------------------------+

 For translation see https://www.transifex.com/projects/p/roundcube-webmail/resource/messages/
*/

$messages['errortitle']  = 'Произошла ошибка!';
$messages['loginfailed']  = 'Неудачная попытка входа.';
$messages['cookiesdisabled'] = 'Ваш браузер не принимает cookie.';
$messages['sessionerror'] = 'Ваша сессия устарела.';
$messages['cookiesmismatch'] = 'Обнаружено несоответствие cookie. Очистите их в браузере.';
$messages['storageerror'] = 'Неудачное соединение с IMAP сервером';
$messages['servererror'] = 'Ошибка сервера!';
$messages['servererrormsg'] = 'Ошибка сервера: $msg';
$messages['accountlocked'] = 'Слишком много неудачных попыток входа. Попробуйте позже.';
$messages['connerror'] = 'Ошибка соединения (Сбой подключения к серверу)!';
$messages['dberror'] = 'Ошибка базы данных!';
$messages['dbconnerror'] = 'Невозможно подключиться к базе данных.';
$messages['windowopenerror'] = 'Всплывающее окно было заблокировано!';
$messages['requesttimedout'] = 'Превышено время ожидания запроса';
$messages['errorreadonly'] = 'Невозможно выполнить операцию. Папка доступна только для чтения.';
$messages['errornoperm'] = 'Невозможно выполнить операцию. Доступ запрещён.';
$messages['erroroverquota'] = 'Невозможно выполнить операцию. Нет свободного места на диске.';
$messages['erroroverquotadelete'] = 'Нет свободного места на диске. Используйте SHIFT+DEL для удаления сообщения.';
$messages['invalidrequest'] = 'Неверный запрос! Информация не сохранена.';
$messages['invalidhost'] = 'Неверное имя сервера.';
$messages['nomessagesfound'] = 'Сообщений не найдено';
$messages['loggedout'] = 'Ваша сессия успешно завершена. Всего доброго!';
$messages['mailboxempty'] = 'Почтовый ящик пуст';
$messages['nomessages'] = 'Сообщения отсутствуют';
$messages['refreshing'] = 'Обновление...';
$messages['loading'] = 'Загрузка...';
$messages['uploading'] = 'Файл загружается…';
$messages['attaching'] = 'Добавление вложения...';
$messages['uploadingmany'] = 'Загрузка файлов...';
$messages['loadingdata'] = 'Загрузка данных...';
$messages['checkingmail'] = 'Проверка новых сообщений...';
$messages['sendingmessage'] = 'Отправка сообщения...';
$messages['messagesent'] = 'Сообщение отправлено.';
$messages['savingmessage'] = 'Сохранение сообщения...';
$messages['messagesaved'] = 'Сохранено в Черновиках.';
$messages['successfullysaved'] = 'Сохранено.';
$messages['savingresponse'] = 'Сохранение текста ответа...';
$messages['deleteresponseconfirm'] = 'Вы действительно хотите удалить этот текст ответа?';
$messages['nocontactselected'] = 'Вы не выбрали контакт.';
$messages['addedsuccessfully'] = 'Контакт добавлен в адресную книгу.';
$messages['contactexists'] = 'Контакт с этим адресом e-mail уже существует.';
$messages['contactnameexists'] = 'Контакт с таким именем уже существует.';
$messages['blockedimages'] = 'В целях безопасности загрузка изображений заблокирована.';
$messages['blockedresources'] = 'В целях безопасности загрузка внешних ресурсов заблокирована.';
$messages['suspiciousemail'] = 'Это сообщение содержит подозрительные адреса электронной почты, которые могут быть мошенническими.';
$messages['bccemail'] = 'Переадресованное сообщение(я) содержит конфиденциальных получателей (Bcc).';
$messages['bouncehint'] = 'Сообщение будет отправлено без каких-либо изменений, то есть с использованием исходного текста, заголовков и получателей.';
$messages['encryptedmessage'] = 'Сообщение зашифровано и не может быть показано. Обратитесь к администратору сервера.';
$messages['externalmessagedecryption'] = 'Это зашифрованное сообщение, оно может быть расшифровано с помощью расширением вашего браузера.';
$messages['nopubkeyfor'] = 'Не найден действительный открытый ключ для $email';
$messages['nopubkeyforsender'] = 'Отсутствует действительный открытый ключ для удостоверения вашей личности отправителя. Вы хотите зашифровать сообщение только для получателей?';
$messages['encryptnoattachments'] = 'Уже загруженные вложения не могут быть зашифрованы. Пожалуйста, повторно добавьте их в редакторе шифрования.';
$messages['searchpubkeyservers'] = 'Вы хотите выполнить поиск серверов открытых ключей для отсутствующих ключей?';
$messages['encryptpubkeysfound'] = 'Следующие открытые ключи были найдены:';
$messages['keyservererror'] = 'Не удалось получить ключ от сервера ключей.';
$messages['keyimportsuccess'] = 'Открытый ключ $key успешно импортирован в хранилище ключей.';
$messages['nocontactsfound'] = 'Контакты не найдены.';
$messages['contactnotfound'] = 'Требуемый контакт не найден.';
$messages['contactsearchonly'] = 'Для просмотра контактов из выбранной адресной книги необходимо использовать форму поиска.';
$messages['sendingfailed'] = 'Не удалось отправить сообщение.';
$messages['senttooquickly'] = 'Вы должны подождать $sec сек. для отправки сообщения.';
$messages['errorsavingsent'] = 'Произошла ошибка при сохранении отправленного сообщения.';
$messages['errorsaving'] = 'В процессе сохранения произошла ошибка.';
$messages['errormoving'] = 'Не удалось переместить сообщение(я).';
$messages['errorcopying'] = 'Не удалось скопировать сообщение(я).';
$messages['errordeleting'] = 'Не удалось удалить сообщение(я).';
$messages['errormarking'] = 'Не удалось пометить сообщение(я).';
$messages['alerttitle'] = 'Внимание';
$messages['confirmationtitle'] = 'Внимание';
$messages['deletecontactconfirm']  = 'Вы действительно хотите удалить выделенные контакты?';
$messages['deletegroupconfirm']  = 'Вы действительно хотите удалить выделенную группу?';
$messages['deletemessagesconfirm'] = 'Вы действительно хотите удалить выбранные сообщения?';
$messages['deletefolderconfirm']  = 'Вы действительно хотите удалить эту папку?';
$messages['movefolderconfirm']  = 'Вы действительно хотите удалить эту папку?';
$messages['purgefolderconfirm']  = 'Вы действительно хотите удалить все сообщения в этой папке?';
$messages['contactdeleting'] = 'Удаление контакта(ов)...';
$messages['groupdeleting'] = 'Удаление группы...';
$messages['folderdeleting'] = 'Удаление папки...';
$messages['foldermoving'] = 'Перемещение папки...';
$messages['foldersubscribing'] = 'Подписать папку...';
$messages['folderunsubscribing'] = 'Отписать папку...';
$messages['formincomplete'] = 'Заполнены не все поля формы.';
$messages['noemailwarning'] = 'Пожалуйста, введите корректный адрес электронной почты.';
$messages['nonamewarning']  = 'Пожалуйста, введите имя.';
$messages['nopagesizewarning'] = 'Пожалуйста, введите размер страницы.';
$messages['nosenderwarning'] = 'Пожалуйста, введите адрес электронной почты отправителя.';
$messages['recipientsadded'] = 'Получатель(и) успешно добавлен.';
$messages['norecipientwarning'] = 'Пожалуйста, введите хотя бы одного получателя.';
$messages['disclosedreciptitle'] = 'Слишком много внешних получателей';
$messages['nosubjectwarning']  = 'Поле Тема не заполнено. Хотите заполнить его сейчас?';
$messages['nosubjecttitle']  = 'Без темы';
$messages['nobodywarning'] = 'Отправить сообщение без текста?';
$messages['notsentwarning'] = 'Текущее сообщение не отправлено и не сохранено. Удалить его?';
$messages['restoresavedcomposedata'] = 'Найдено ранее составленное, но неотправленное сообщение.\n\nТема: $subject\nСохранено: $date\n\nХотите восстановить это сообщение?';
$messages['nosearchname'] = 'Пожалуйста, введите имя или адрес E-Mail';
$messages['notuploadedwarning'] = 'Вложения загружены не полностью. Подождите или отмените загрузку.';
$messages['searchsuccessful'] = 'Найденных сообщений - $nr';
$messages['contactsearchsuccessful'] = '$nr контактов найдено.';
$messages['searchnomatch'] = 'Ничего не найдено.';
$messages['searching'] = 'Поиск...';
$messages['checking'] = 'Проверка...';
$messages['stillsearching'] = 'Поиск...';
$messages['nospellerrors'] = 'Орфографических ошибок не найдено.';
$messages['folderdeleted'] = 'Папка удалена.';
$messages['foldersubscribed'] = 'Папка подписана';
$messages['folderunsubscribed'] = 'Папка отписана';
$messages['folderpurged'] = 'Папка очищена.';
$messages['folderexpunged'] = 'Папка сжата.';
$messages['deletedsuccessfully'] = 'Удалено.';
$messages['converting'] = 'Удаление форматирования сообщения...';
$messages['messageopenerror'] = 'Невозможно загрузить сообщение с сервера.';
$messages['filelinkerror'] = 'Сбой добавления вложения.';
$messages['fileuploaderror'] = 'Ошибка загрузки файла.';
$messages['csvfilemismatch'] = 'Импорт нескольких файлов CSV с разными полями не поддерживается.';
$messages['filesizeerror'] = 'Загруженный файл больше максимального размера в $size.';
$messages['filecounterror'] = 'Вы можете загрузить максимум $count файлов одновременно.';
$messages['msgsizeerror'] = 'Не удалось прикрепить файл. Превышен максимальный размер сообщения: ($size).';
$messages['msgsizeerrorfwd'] = 'Превышен максимальный размер сообщения ($size). $num сообщение(й) не было(и) прикреплено(ы).';
$messages['copysuccess'] = 'Успешно скопировано $nr контактов.';
$messages['movesuccess'] = 'Успешно перемещено $nr контактов.';
$messages['copyerror'] = 'Контакты не скопированы.';
$messages['moveerror'] = 'Контакты не перемещены.';
$messages['sourceisreadonly'] = 'Данный источник адресов только для чтения.';
$messages['errorsavingcontact'] = 'Невозможно сохранить адрес контакта.';
$messages['movingmessage'] = 'Перемещение сообщения(й)…';
$messages['copyingmessage'] = 'Копирование сообщения(й)...';
$messages['copyingcontact'] = 'Копирование контакта(ов)...';
$messages['movingcontact'] = 'Перемещение контакта(ов)...';
$messages['deletingmessage'] = 'Удаление сообщения(й)...';
$messages['markingmessage'] = 'Выделение сообщения(й)...';
$messages['addingmember'] = 'Добавление контакта(ов) в группу...';
$messages['removingmember'] = 'Удаление контакта(ов) из группы...';
$messages['receiptsent'] = 'Уведомление о прочтении отправлено.';
$messages['errorsendingreceipt'] = 'Уведомление о прочтении не отправлено.';
$messages['deleteidentityconfirm'] = 'Вы на самом деле хотите удалить эту сущность?';
$messages['nodeletelastidentity'] = 'Вы не можете удалить этот профиль, он у вас последний.';
$messages['forbiddencharacter'] = 'Имя папки содержит недопустимые символы.';
$messages['selectimportfile'] = 'Выберите файл для загрузки.';
$messages['addresswriterror'] = 'Выбранная адресная книга недоступна для записи.';
$messages['contactaddedtogroup'] = 'Контакты добавлены в эту группу.';
$messages['contactremovedfromgroup'] = 'Контакты удалены из этой группы.';
$messages['nogroupassignmentschanged'] = 'Распределение по группам не изменено.';
$messages['importwait'] = 'Импортирование, пожалуйста, подождите...';
$messages['importformaterror'] = 'Ошибка импорта! Загруженный файл имеет неизвестный  формат данных.';
$messages['importconfirm'] = '<b>Успешно импортировано $inserted контактов</b>';
$messages['importmessagesuccess'] = '$nr сообщений успешно импортировано.';
$messages['importmessageerror'] = 'Сбой импорта! Загруженный файл не является файлом сообщения или почтового ящика';
$messages['opnotpermitted'] = 'Действие запрещено!';
$messages['nofromaddress'] = 'В выбранном профиле не хватает адреса электронной почты.';
$messages['editorwarning'] = 'Переключение типа редактора может привести к потере  форматирования текста. Продолжить?';
$messages['httpreceivedencrypterror'] = 'На сервере возникла неизбежная ошибка. Срочно свяжитесь с Вашим администратором. <b>Ваше сообщение не может быть отправлено.</b>';
$messages['smtpconnerror'] = 'SMTP ошибка ($code): Сбой соединения с сервером.';
$messages['smtpautherror'] = 'SMTP ошибка ($code): Ошибка авторизации.';
$messages['smtpfromerror'] = 'SMTP ошибка ($code): Невозможно установить отправителя "$from" ($msg)';
$messages['smtptoerror'] = 'SMTP ошибка ($code): Невозможно добавить получателя "$to" ($msg)';
$messages['smtprecipientserror'] = 'SMTP ошибка: Невозможно обработать список получателей.';
$messages['smtputf8error'] = 'Ошибка SMTP: сервер не поддерживает Unicode в адресе электронной почты.';
$messages['smtpsizeerror'] = 'Ошибка SMTP: Размер сообщения превышает ограничения сервера ($limit)';
$messages['smtperror'] = 'SMTP ошибка: $msg';
$messages['emailformaterror'] = 'Неверный адрес: $email';
$messages['toomanyrecipients'] = 'Слишком много получателей. Уменьшите их количество до $max.';
$messages['maxgroupmembersreached'] = 'Размер группы больше максимально разрешенного - $max.';
$messages['internalerror'] = 'Произошла внутренняя ошибка. Попробуйте ещё раз.';
$messages['contactdelerror'] = 'Не возможно удалить контакт(ы).';
$messages['contactdeleted'] = 'Контакт(ы) успешно удален(ы).';
$messages['contactrestoreerror'] = 'Не удалось восстановить удалённый(е) контакт(ы).';
$messages['contactrestored'] = 'Контакт(ы) успешно восстановлен(ы).';
$messages['groupdeleted'] = 'Группа успешно удалена.';
$messages['grouprenamed'] = 'Группа успешно переименована.';
$messages['groupcreated'] = 'Группа успешно создана.';
$messages['savedsearchdeleted'] = 'Сохранённый запрос успешно удалён.';
$messages['savedsearchdeleteerror'] = 'Не удалось удалить сохранённый запрос.';
$messages['savedsearchcreated'] = 'Сохранённый запрос успешно создан.';
$messages['savedsearchcreateerror'] = 'Не удалось создать сохранённый запрос.';
$messages['messagedeleted'] = 'Сообщение(я) успешно удалено(ы).';
$messages['messagemoved'] = 'Сообщение(я) успешно перемещено(ы).';
$messages['messagemovedtotrash'] = 'Сообщение(я) успешно перемещено в корзину.';
$messages['messagecopied'] = 'Сообщение(я) успешно скопировано(ы).';
$messages['messagemarked'] = 'Сообщение(я) успешно выделено(ы).';
$messages['autocompletechars'] = 'Введите, как минимум, $min символов для автодополнения.';
$messages['autocompletemore'] = 'Поиск возвратил слишком много результатов. Пожалуйста, уточните.';
$messages['namecannotbeempty'] = 'Имя не может быть пустым.';
$messages['nametoolong'] = 'Слишком длинное имя.';
$messages['namedotforbidden'] = 'Имя папки не может начинаться с точки';
$messages['folderupdated'] = 'Папка обновлена.';
$messages['foldercreated'] = 'Папка создана.';
$messages['invalidimageformat'] = 'Неверный формат изображения.';
$messages['mispellingsfound'] = 'В сообщении обнаружены орфографические ошибки.';
$messages['parentnotwritable'] = 'Не удается создать/переместить папку в выбранную родительскую папку. Нет прав доступа.';
$messages['messagetoobig'] = 'Часть сообщения слишком велика для обработки.';
$messages['attachmentvalidationerror'] = 'Это вложение подозрительно, потому что его тип не соответствует типу, объявленному в сообщении. Если Вы не доверяете отправителю, Вы не должны открывать его в браузере, потому что оно может содержать вредоносное содержимое.<br/><br/><em>Ожидаемое: $expected; найденое: $detected</em>';
$messages['noscriptwarning'] = 'Внимание: Данному сервису веб-почты требуется Javascript! Для того, чтобы его использовать необходимо включить поддержку Javascript в настройках вашего браузера.';
$messages['messageissent'] = 'Сообщение уже отправлено, но еще не сохранено. Вы хотите сохранить его?';
$messages['errnotfound'] = 'Файл не найден';
$messages['errnotfoundexplain'] = 'Требуемый ресурс не найден';
$messages['errfailedrequest'] = 'Неудавшийся запрос';
$messages['errauthorizationfailed'] = 'Сбой авторизации';
$messages['errunauthorizedexplain'] = 'Не удалось проверить, что вы имеете право доступа к этой услуге!';
$messages['errrequestcheckfailed'] = 'Запрос не прошел проверку';
$messages['errcsrfprotectionexplain'] = "Для вашей безопасности, доступ к этому ресурсу защищен от CSRF.\nЕсли вы видите это, вы, вероятно, не вышли, прежде чем покинуть веб-приложения.\n\nДля продолжения теперь потребуется действия человека.";
$messages['errcontactserveradmin'] = 'Обратитесь к администратору сервера.';
$messages['clicktoresumesession'] = 'Нажмите здесь, чтобы возобновить предыдущий сеанс';
$messages['errcomposesession'] = 'Ошибка сеанса написания письма';
$messages['errcomposesessionexplain'] = 'Требуемый сеанс написания письма не найден.';
$messages['clicktocompose'] = 'Нажмите для создания нового сообщения';
$messages['nosupporterror'] = 'Эта функция не поддерживается Вашим веб-браузером.';
$messages['siginserted'] = 'Подпись вставлена успешно.';
$messages['responseinserted'] = 'Ответ успешно вставлен.';
$messages['noresponsesavailable'] = 'Нет доступных ответов';
$messages['listempty'] = 'Список пуст.';
$messages['listusebutton'] = 'Используйте кнопку Создать, чтобы добавить новую запись.';
$messages['keypaircreatesuccess'] = 'Новая пара ключей была создана для $identity.';
$messages['emptyattachment'] = 'Это вложение похоже на пустое <br>Пожалуйста свяжитесь с отправителем для уточнения.';
$messages['oauthloginfailed'] = 'Ошибка OAuth авторизации. Пожалуйста, попробуйте еще раз.';
