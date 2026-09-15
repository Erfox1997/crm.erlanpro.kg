# ErlanPro Messenger — Android (Capacitor)

Приложение только для мессенджера. Пока **без Play Market**: собираете APK и ставите вручную.

Telegram Mini App остаётся для сотрудников без Android.

## Что нужно

1. Node.js 20+
2. Android Studio (SDK 34+)
3. Firebase проект + `google-services.json`
4. На сервере CRM в `.env`:

```env
FCM_SERVER_KEY=ваш_legacy_server_key_из_firebase
```

Firebase Console → Project settings → Cloud Messaging → **Cloud Messaging API (Legacy)** → Server key.

После правки `.env` на проде:

```bash
php artisan config:clear
php artisan migrate --force
```

## Сборка APK

```bash
cd mobile
npm install
# подставьте свой прод-URL при необходимости:
# set CRM_APP_URL=https://crm.erlanpro.kg   (Windows)
# export CRM_APP_URL=https://crm.erlanpro.kg (Linux/macOS)

npx cap add android
# положите google-services.json в mobile/android/app/
npx cap sync android
npx cap open android
```

В Android Studio: **Build → Build Bundle(s) / APK(s) → Build APK(s)**.

APK раздаёте сотрудникам (файл / ссылка). При первом входе — логин CRM, дальше только мессенджер.

## Как работает

- Capacitor открывает `https://…/app` → редирект на `/messenger?mini=1&app=android`
- UI как в Telegram Mini App (без бокового меню CRM)
- FCM-токен регистрируется на `POST /device-tokens`
- Входящее сообщение → push + badge/count (где поддерживает лаунчер)

## Через месяц — Play Market

1. Подписать release AAB в Android Studio
2. Создать приложение в Google Play Console
3. Privacy policy / Data safety
4. Загрузить AAB и пройти модерацию

Пока достаточно APK для теста.
