# ErlanPro Messenger — Android (Capacitor)

Приложение только для мессенджера. Пока **без Play Market**: собираете APK и ставите вручную.

Telegram Mini App остаётся для сотрудников без Android.

## Что нужно

1. Node.js 20+
2. Android Studio (SDK 34+)
3. Firebase проект + `google-services.json`
4. На сервере CRM — **Firebase HTTP v1** (не Legacy Server key):

Firebase Console → ⚙️ Project settings → **Service accounts** → **Generate new private key**  
Сохраните JSON как `storage/app/firebase-credentials.json` (на проде и локально, файл не в git).

В `.env`:

```env
FCM_CREDENTIALS=storage/app/firebase-credentials.json
```

После правки `.env` на проде:

```bash
php artisan config:clear
php artisan migrate --force
```

## Сборка APK

Нужны: **JDK 21**, Android SDK (из Android Studio).

```powershell
cd mobile
npm install

# Windows: если путь к проекту с кириллицей — уже включено android.overridePathCheck в gradle.properties
$env:JAVA_HOME="C:\Program Files\Microsoft\jdk-21.0.12.101-hotspot"
$env:ANDROID_HOME="$env:LOCALAPPDATA\Android\Sdk"
$env:Path="$env:JAVA_HOME\bin;$env:ANDROID_HOME\platform-tools;$env:Path"

npx cap add android   # только первый раз
Copy-Item www\google-services.json android\app\google-services.json -Force
npx cap sync android

cd android
.\gradlew.bat assembleDebug
```

APK: `mobile\android\app\build\outputs\apk\debug\app-debug.apk`

Или через Android Studio: `npx cap open android` → **Build → Build APK(s)**.


## Как работает

- Capacitor открывает `https://…/app` → редирект на `/messenger?mini=1&app=android`
- UI как в Telegram Mini App (без бокового меню CRM)
- FCM-токен регистрируется на `POST /device-tokens`
- Входящее сообщение → push с реальным числом непрочитанных
- Бейдж на иконке синхронизируется с непрочитанными: при открытии приложения **не сбрасывается**, пока чаты не прочитаны; при прочтении обновляется/очищается
- Нужен плагин `@capawesome/capacitor-badge` (уже в `mobile/`) — после обновления кода пересоберите APK (`npx cap sync android` + `gradlew assembleDebug`)

## Через месяц — Play Market

1. Подписать release AAB в Android Studio
2. Создать приложение в Google Play Console
3. Privacy policy / Data safety
4. Загрузить AAB и пройти модерацию

Пока достаточно APK для теста.
