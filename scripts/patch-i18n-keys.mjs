import fs from 'fs';

const ruPath = 'resources/js/i18n/locales/ru.json';
const enPath = 'resources/js/i18n/locales/en.json';
const ru = JSON.parse(fs.readFileSync(ruPath, 'utf8'));
const en = JSON.parse(fs.readFileSync(enPath, 'utf8'));

function set(obj, path, value) {
    const parts = path.split('.');
    let cur = obj;
    for (let i = 0; i < parts.length - 1; i++) {
        if (!cur[parts[i]] || typeof cur[parts[i]] !== 'object') {
            cur[parts[i]] = {};
        }
        cur = cur[parts[i]];
    }
    cur[parts[parts.length - 1]] = value;
}

const pairs = {
    'comments.emptySync': [
        'Комментариев пока нет. Нажмите «Обновить», чтобы синхронизировать.',
        'No comments yet. Click Refresh to sync.',
    ],
    'employees.subtitle': [
        'Создайте аккаунты сотрудников или загрузите их из Excel.',
        'Create employee accounts or import them from Excel.',
    ],
    'employees.byTariff': ['По тарифу: {label}', 'Plan limit: {label}'],
    'employees.countUnlimited': ['{n} сотрудников', '{n} employees'],
    'employees.countLimited': [
        '{used} / {max} сотрудников',
        '{used} / {max} employees',
    ],
    'employees.telegramMiniAppHint': [
        'Telegram Mini App: @{bot} — укажите Telegram username сотрудника (без @). Доступ только у привязанных аккаунтов: сотрудник открывает бота → /start → «Открыть мессенджер». Владелец компании указывает свой Telegram в профиле.',
        'Telegram Mini App: @{bot} — set the employee Telegram username (without @). Only linked accounts have access: employee opens the bot → /start → “Open messenger”. The company owner sets their Telegram in the profile.',
    ],
    'employees.limitExhausted': [
        'Лимит сотрудников по тарифу исчерпан. Чтобы добавить новых, смените тариф.',
        'Employee limit for your plan is reached. Change the plan to add more.',
    ],
    'employees.needPositions': [
        'Сначала создайте хотя бы одну должность на странице',
        'First create at least one role on the',
    ],
    'employees.needPositionsLink': ['Должности', 'Roles'],
    'employees.searchPh': [
        'Поиск по ФИО, почте или должности...',
        'Search by name, email, or role...',
    ],
    'employees.importing': ['Импорт...', 'Importing...'],
    'employees.excelHint': [
        'Excel: колонки ФИО, Почта, Пароль, Должность (название должно совпадать с созданной должностью).',
        'Excel: columns Name, Email, Password, Role (role name must match an existing role).',
    ],
    'employees.notAdded': ['Сотрудники ещё не добавлены', 'No employees added yet'],
    'employees.mail': ['Почта', 'Email'],
    'employees.dismissedLabel': ['Уволен', 'Dismissed'],
    'employees.waitingStart': ['ожидает /start', 'waiting for /start'],
    'employees.confirmFireDetail': [
        'Уволить «{name}»?\n\nПродажи и история сохранятся, вход в кабинет будет закрыт.',
        'Dismiss “{name}”?\n\nSales and history are kept; cabinet access will be closed.',
    ],
    'employees.confirmRestore': [
        'Восстановить доступ для «{name}»?',
        'Restore access for “{name}”?',
    ],
    'employees.newEmployee': ['Новый сотрудник', 'New employee'],
    'employees.newHint': [
        'Аккаунт сразу сможет войти в CRM с указанным паролем.',
        'The account can sign in to the CRM immediately with this password.',
    ],
    'employees.selectPosition': ['Выберите должность', 'Select a role'],
    'employees.passwordConfirm': ['Повтор пароля', 'Confirm password'],
    'employees.telegramUsername': ['Telegram username', 'Telegram username'],
    'employees.telegramFieldHint': [
        'Без @. Нужен для входа в Mini App и пушей. После создания сотрудник пишет /start боту.',
        'Without @. Needed for Mini App login and push. After creation the employee sends /start to the bot.',
    ],
    'employees.editEmployee': ['Редактировать сотрудника', 'Edit employee'],
    'employees.newPasswordOptional': [
        'Новый пароль (необязательно)',
        'New password (optional)',
    ],
    'employees.editTitleAttr': ['Редактировать', 'Edit'],

    'positions.subtitle': [
        'Создайте должности и выберите, к каким страницам есть доступ.',
        'Create roles and choose which pages they can access.',
    ],
    'positions.add': ['Добавить должность', 'Add role'],
    'positions.notCreated': ['Должности ещё не созданы', 'No roles created yet'],
    'positions.noPageAccess': ['Нет доступа к страницам', 'No page access'],
    'positions.employeesCount': ['Сотрудников: {n}', 'Employees: {n}'],
    'positions.new': ['Новая должность', 'New role'],
    'positions.newHint': [
        'Укажите название и отметьте доступные разделы CRM.',
        'Enter a name and mark the CRM sections available.',
    ],
    'positions.namePh': ['Например: Менеджер', 'e.g. Manager'],
    'positions.edit': ['Редактировать должность', 'Edit role'],
    'positions.editTitleAttr': ['Редактировать', 'Edit'],
    'positions.deleteTitleAttr': ['Удалить', 'Delete'],

    'clientFields.addFields': ['Добавить поля', 'Add fields'],
    'clientFields.notAdded': ['Поля ещё не добавлены', 'No fields added yet'],
    'clientFields.chatName': ['(имя в чате)', '(chat name)'],
    'clientFields.newFields': ['Новые поля', 'New fields'],
    'clientFields.newHint': [
        'Добавьте сразу несколько полей — например: Имя, Телефон, Адрес, Область.',
        'Add several fields at once — e.g. Name, Phone, Address, Region.',
    ],
    'clientFields.fieldN': ['Поле {n}', 'Field {n}'],
    'clientFields.keyLatin': ['Ключ (латиница)', 'Key (Latin)'],
    'clientFields.labelPh': ['Например: Имя', 'e.g. Name'],
    'clientFields.type': ['Тип', 'Type'],
    'clientFields.messengerHint': [
        'Это значение показывается в шапке чата вместо имени из Telegram / Instagram.',
        'This value is shown in the chat header instead of the Telegram / Instagram name.',
    ],
    'clientFields.messengerHintEdit': [
        'Это значение показывается в шапке чата вместо имени из мессенджера.',
        'This value is shown in the chat header instead of the messenger name.',
    ],
    'clientFields.messengerTaken': [
        'Уже назначено другому полю — снимите галочку там или отредактируйте его.',
        'Already assigned to another field — clear it there or edit that field.',
    ],
    'clientFields.optionsLines': [
        'Варианты (по одному в строке)',
        'Options (one per line)',
    ],
    'clientFields.addMore': ['+ Добавить ещё поле', '+ Add another field'],
    'clientFields.saveAndMore': [
        'Сохранить и добавить ещё',
        'Save and add more',
    ],
    'clientFields.saveAll': ['Сохранить все', 'Save all'],
    'clientFields.editField': ['Редактировать поле', 'Edit field'],
    'clientFields.requiredField': ['Обязательное поле', 'Required field'],
    'clientFields.needOne': [
        'Добавьте хотя бы одно поле с названием.',
        'Add at least one field with a name.',
    ],
    'clientFields.type.email': ['Email', 'Email'],
    'clientFields.editTitleAttr': ['Редактировать', 'Edit'],
    'clientFields.deleteTitleAttr': ['Удалить', 'Delete'],

    'chatDistribution.subtitleFull': [
        'Выберите, как новые входящие диалоги будут попадать к сотрудникам.',
        'Choose how new inbound chats are assigned to employees.',
    ],
    'chatDistribution.agentsWithAccess': [
        'Сотрудники с доступом к Мессенджеру',
        'Employees with Messenger access',
    ],
    'chatDistribution.noAgents': [
        'Пока нет сотрудников с доступом к Мессенджеру. Создайте должности с этим правом и назначьте сотрудников.',
        'No employees have Messenger access yet. Create roles with that permission and assign employees.',
    ],
    'chatDistribution.noPosition': ['Без должности', 'No role'],
    'chatDistribution.saving': ['Сохранение...', 'Saving...'],

    'tariffs.subtitle': [
        'Прозрачные условия — выберите подходящий план и продолжайте работу без ограничений',
        'Clear terms — pick a plan and keep working without limits',
    ],
    'tariffs.perDays': ['за {days} дн.', 'for {days} d'],
    'tariffs.featureFull': [
        'Полный доступ ко всем функциям',
        'Full access to all features',
    ],
    'tariffs.featureDuration': [
        'Срок действия — {days} дн.',
        'Valid for {days} d',
    ],
    'tariffs.featureEmployees': ['До {n} сотрудников', 'Up to {n} employees'],
    'tariffs.featureEmployeesUnlimited': [
        'Без ограничения по сотрудникам',
        'No employee limit',
    ],
    'tariffs.featureMessages': [
        'Хранение сообщений — {n} дн.',
        'Message retention — {n} d',
    ],
    'tariffs.featureMessagesUnlimited': [
        'Хранение сообщений без ограничения',
        'Unlimited message retention',
    ],
    'tariffs.featureSupport': [
        'Поддержка при подключении',
        'Onboarding support',
    ],
    'tariffs.selectedHint': [
        'Вы выбрали тариф {name} — оплатите по реквизитам ниже и отправьте скриншот чека в WhatsApp.',
        'You selected plan {name} — pay using the details below and send a receipt screenshot via WhatsApp.',
    ],
    'tariffs.noRequisites': [
        'Реквизиты пока не добавлены. Обратитесь к администратору.',
        'Payment details are not set yet. Contact the administrator.',
    ],
    'tariffs.afterPayHint': [
        'После оплаты отправьте скриншот чека в WhatsApp и укажите выбранный тариф.',
        'After payment, send a receipt screenshot via WhatsApp and mention the selected plan.',
    ],
    'tariffs.qrAlt': ['QR для оплаты', 'Payment QR'],
    'tariffs.whatsappPayMessage': [
        'Здравствуйте! Оплатил тариф «{name}» в CRM ErlanPro. Отправляю скриншот чека.',
        'Hello! I paid for the “{name}” plan in CRM ErlanPro. Sending a receipt screenshot.',
    ],
    'tariffs.qrLabel': ['QR для оплаты', 'Payment QR'],

    'quickReplies.audioFile': [
        'Аудиофайл (M4A, MP3, WAV)',
        'Audio file (M4A, MP3, WAV)',
    ],
    'quickReplies.imageFile': [
        'Изображение (JPG, PNG, WEBP)',
        'Image (JPG, PNG, WEBP)',
    ],
    'quickReplies.newAudioOptional': [
        'Новый аудиофайл (необязательно)',
        'New audio file (optional)',
    ],
    'quickReplies.newImageOptional': [
        'Новое изображение (необязательно)',
        'New image (optional)',
    ],
    'quickReplies.voiceTemplate': ['Голосовой шаблон', 'Voice template'],
    'quickReplies.imageNoCaption': [
        'Изображение без подписи',
        'Image without caption',
    ],
    'quickReplies.emptyHintFull': [
        'Создайте первый шаблон или загрузите готовые ответы из Excel. В мессенджере они будут доступны через команду «/».',
        'Create the first template or import replies from Excel. In Messenger they are available via the “/” command.',
    ],
    'quickReplies.importFromExcel': ['Импорт из Excel', 'Import from Excel'],
    'quickReplies.noneForQuery': [
        'По запросу «{q}» ничего не найдено.',
        'Nothing found for “{q}”.',
    ],
    'quickReplies.createHint': [
        'Создайте быстрый ответ для мессенджера',
        'Create a quick reply for Messenger',
    ],
    'quickReplies.availableAs': [
        'В чате будет доступно как',
        'In chat it will be available as',
    ],
    'quickReplies.pickNewFile': ['Выбрать новый файл', 'Choose a new file'],
    'quickReplies.commandPh': ['компофф', 'welcome'],

    'broadcasts.subtitleFull': [
        'Отправка сообщений по воронке или по данным клиента — в фоне, с паузами и по расписанию.',
        'Send messages by funnel or client fields — in the background, with delays and scheduling.',
    ],
    'broadcasts.emptyFull': [
        'Рассылок пока нет. Создайте первую — страница не будет зависать даже на тысячах получателей.',
        'No broadcasts yet. Create the first one — the page won’t freeze even with thousands of recipients.',
    ],
    'broadcasts.newHint': [
        'Сообщения уходят в фоне через очередь — браузер не ждёт отправку всем клиентам.',
        'Messages are queued in the background — the browser doesn’t wait for every client.',
    ],
    'broadcasts.nameOptional': [
        'Название (необязательно)',
        'Name (optional)',
    ],
    'broadcasts.namePh': [
        'Например: Акция март — женщины',
        'e.g. March promo — women',
    ],
    'broadcasts.byFunnelHint': [
        'Клиенты на выбранном этапе',
        'Clients on the selected stage',
    ],
    'broadcasts.byFieldsHint': [
        'Фильтр по полям (пол и т.д.)',
        'Filter by fields (gender, etc.)',
    ],
    'broadcasts.needFields': [
        'Сначала создайте поля в разделе «Данные клиента».',
        'First create fields in Client fields.',
    ],
    'broadcasts.field': ['Поле', 'Field'],
    'broadcasts.value': ['Значение', 'Value'],
    'broadcasts.valuePh': ['Например: женский', 'e.g. female'],
    'broadcasts.moreFilter': ['+ Ещё фильтр', '+ Another filter'],
    'broadcasts.bodyPh': ['Текст рассылки…', 'Broadcast text…'],
    'broadcasts.delayHint': [
        'Рекомендуем 3–10 сек, чтобы снизить риск блокировки аккаунта.',
        'We recommend 3–10 sec to reduce account block risk.',
    ],
    'broadcasts.previewErr': [
        'Не удалось посчитать получателей',
        'Could not count recipients',
    ],
    'broadcasts.pipeline': ['Воронка', 'Funnel'],
    'broadcasts.stage': ['Этап', 'Stage'],
    'broadcasts.filters': ['Фильтры:', 'Filters:'],
    'broadcasts.cancelAction': ['Отменить', 'Cancel broadcast'],
    'broadcasts.errors': ['Ошибки', 'Errors'],
    'broadcasts.skippedCol': ['Пропущено', 'Skipped'],
    'broadcasts.delayBetween': [
        'Пауза между сообщениями',
        'Delay between messages',
    ],
    'broadcasts.scheduledAt': ['Запланировано', 'Scheduled'],
    'broadcasts.startedAt': ['Старт', 'Started'],
    'broadcasts.finishedAt': ['Завершено', 'Finished'],
    'broadcasts.autoRefreshDots': [
        'Статус обновляется автоматически…',
        'Status updates automatically…',
    ],
    'broadcasts.comment': ['Комментарий', 'Comment'],
    'broadcasts.time': ['Время', 'Time'],
    'broadcasts.noRecipients': ['Получателей нет', 'No recipients'],
    'broadcasts.sentCol': ['Отправлено', 'Sent'],

    'integrations.apiToken': ['Токен API', 'API token'],
    'integrations.newApiToken': ['Новый токен API', 'New API token'],
    'integrations.wappiTokenHint': [
        'Токен API из личного кабинета Wappi',
        'API token from the Wappi dashboard',
    ],
    'integrations.webhookAuto': [
        'Webhook (настраивается автоматически при сохранении)',
        'Webhook (configured automatically on save)',
    ],
    'integrations.newBotToken': ['Новый токен бота', 'New bot token'],
    'integrations.botFatherHint': [
        'Токен от @BotFather',
        'Token from @BotFather',
    ],
    'integrations.telegramHint': [
        'Создайте бота через @BotFather и вставьте HTTP API токен. Клиенты должны сначала написать боту в Telegram.',
        'Create a bot via @BotFather and paste the HTTP API token. Clients must message the bot on Telegram first.',
    ],
    'integrations.apiKey': ['API-ключ', 'API key'],
    'integrations.newShopKey': [
        'Новый ключ из магазина',
        'New key from the shop',
    ],
    'integrations.shopKeyPh': [
        'sk_… из раздела «Ключ API» в магазине',
        'sk_… from the “API key” section in the shop',
    ],
    'integrations.shopHint': [
        'Ключ создаётся в магазине: Настройки → Ключ API. После сохранения можно продавать из мессенджера.',
        'Create the key in the shop: Settings → API key. After saving you can sell from Messenger.',
    ],
    'integrations.openaiLeaveEmpty': [
        'Оставьте пустым, чтобы не менять ключ',
        'Leave empty to keep the current key',
    ],
    'integrations.openaiFrom': ['Ключ из', 'Key from'],
    'integrations.openaiMessenger': [
        'В мессенджере появится кнопка ИИ для правки ответа.',
        'An AI button will appear in Messenger to refine replies.',
    ],
    'integrations.model': ['Модель', 'Model'],
    'integrations.modelHint': [
        'Если модель недоступна в Limits проекта OpenAI — выберите другую или разрешите её в настройках проекта.',
        'If the model is unavailable in OpenAI project Limits — pick another or enable it in project settings.',
    ],
    'integrations.newToken': ['Новый токен', 'New token'],
    'integrations.apiTokenGeneric': ['API токен', 'API token'],
    'integrations.oauthConnect': [
        'Подключение {provider} через Meta OAuth',
        'Connecting {provider} via Meta OAuth',
    ],

    'shopSales.month': ['Месяц', 'Month'],
    'shopSales.avgReplyFull': [
        'Среднее время ответа — от входящего сообщения клиента до первого ответа менеджера (по назначенному чату).',
        'Average reply time — from the client’s inbound message to the manager’s first reply (on the assigned chat).',
    ],
    'shopSales.salesCount': ['Продаж', 'Sales'],
    'shopSales.sum': ['Сумма', 'Amount'],
    'shopSales.integrations': ['Интеграции', 'Integrations'],
    'shopSales.hoursMinutes': ['{h} ч {m} мин', '{h} h {m} min'],
    'shopSales.minutesSeconds': ['{m} мин {s} сек', '{m} min {s} sec'],
    'shopSales.secondsOnly': ['{s} сек', '{s} sec'],

    'messenger.filterTitleAttr': ['Фильтр по воронке', 'Filter by funnel'],
    'messenger.quickRepliesTitle': ['Быстрые ответы', 'Quick replies'],
    'messenger.refreshTitle': [
        'Обновить новые сообщения',
        'Refresh new messages',
    ],
    'messenger.connectIntegrations': ['интеграциях', 'Integrations'],
    'messenger.connectPrefix': [
        'Подключите Instagram, Facebook, WhatsApp или Telegram в',
        'Connect Instagram, Facebook, WhatsApp, or Telegram in',
    ],
    'messenger.note': ['Заметка', 'Note'],
    'messenger.commandPh': ['например: мбанк', 'e.g. mbank'],
    'messenger.selectEllipsis': ['Выберите...', 'Select...'],
    'messenger.nameTitle': ['Имя: {name}', 'Name: {name}'],
    'messenger.contactTitle': ['Контакт: {contact}', 'Contact: {contact}'],
    'messenger.igFormatLong': [
        'Для Instagram нужен формат M4A/MP4. Откройте CRM в Safari или Edge и попробуйте снова.',
        'Instagram needs M4A/MP4. Open the CRM in Safari or Edge and try again.',
    ],
    'messenger.voicePlain': ['Голосовое сообщение', 'Voice message'],
    'messenger.fieldsPrefix': [
        'Сначала добавьте поля в разделе',
        'First add fields in',
    ],
    'messenger.saleCurrencyLabel': [
        'Чек · {total} {currency}',
        'Receipt · {total} {currency}',
    ],

    'funnels.reorderHintFull': [
        'Переместите этапы стрелками влево и вправо, затем сохраните порядок.',
        'Move stages with the left/right arrows, then save the order.',
    ],
    'funnels.dealTitlePh': [
        'Например, Поставка оборудования',
        'e.g. Equipment delivery',
    ],
    'funnels.newStagesHint': [
        'Воронка: {name}. Можно добавить сразу несколько этапов.',
        'Funnel: {name}. You can add several stages at once.',
    ],
    'funnels.stagePh': ['Например, Согласование', 'e.g. Approval'],
    'funnels.removeRow': ['Убрать строку', 'Remove row'],
    'funnels.pipelineLabel': ['Воронка: {name}', 'Funnel: {name}'],
    'funnels.linkHintFull': [
        'При переносе сделки на этот этап она автоматически попадёт в выбранный этап другой воронки.',
        'When a deal moves to this stage it will also appear on the selected stage of another funnel.',
    ],
    'funnels.selectStage': ['— выберите этап —', '— select a stage —'],
    'funnels.deleteStageBodyFull': [
        'Этап «{name}» будет удалён. На этапе не должно остаться сделок.',
        'Stage “{name}” will be deleted. It must have no deals left.',
    ],
    'funnels.deleteStageBtn': ['Удалить этап', 'Delete stage'],

    'messenger.sell.errServer': [
        'Ошибка сервера ({status}). Проверьте деплой CRM и магазина.',
        'Server error ({status}). Check CRM and shop deployment.',
    ],
    'messenger.sell.errBadJson': [
        'Некорректный ответ сервера',
        'Invalid server response',
    ],
    'messenger.sell.errCatalogStatus': [
        'Не удалось загрузить каталог ({status})',
        'Could not load catalog ({status})',
    ],
    'messenger.sell.errEmptyCatalog': [
        'Каталог пуст. Добавьте товары и склады в магазине.',
        'Catalog is empty. Add products and warehouses in the shop.',
    ],
    'messenger.sell.errLoad': ['Ошибка загрузки', 'Load error'],
    'messenger.sell.draftLoaded': ['Черновик загружен', 'Draft loaded'],
    'messenger.sell.product': ['Товар', 'Product'],
    'messenger.sell.draftSaved': ['Черновик сохранён', 'Draft saved'],
    'messenger.sell.errDraftSave': [
        'Ошибка сохранения черновика',
        'Draft save error',
    ],
    'messenger.sell.errCalc': [
        'Не удалось отправить расчёт',
        'Could not send calculation',
    ],
    'messenger.sell.needFullPay': [
        'Нужна полная оплата. Осталось: {amount} {currency}',
        'Full payment required. Remaining: {amount} {currency}',
    ],
    'messenger.sell.needPayment': [
        'Укажите оплату наличными или безналом.',
        'Enter cash or card payment.',
    ],
};

for (const [path, [r, e]] of Object.entries(pairs)) {
    set(ru, path, r);
    set(en, path, e);
}

fs.writeFileSync(ruPath, JSON.stringify(ru, null, 2) + '\n');
fs.writeFileSync(enPath, JSON.stringify(en, null, 2) + '\n');
console.log('Added', Object.keys(pairs).length, 'key paths');
