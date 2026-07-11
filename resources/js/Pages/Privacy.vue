<script setup>
import BrandLogo from '@/Components/BrandLogo.vue';
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    appName: {
        type: String,
        default: 'CRM',
    },
    contactEmail: {
        type: String,
        default: 'support@erlanpro.kg',
    },
});

const page = usePage();
const branding = page.props.branding ?? {};
</script>

<template>
    <Head title="Политика конфиденциальности" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-3xl items-center justify-between px-4 py-5 sm:px-6"
            >
                <Link href="/">
                    <BrandLogo
                        :name="branding.name ?? 'ErlanPro'"
                        :domain="branding.domain ?? 'crm.erlanpro.kg'"
                        icon-class="h-8 w-8"
                    />
                </Link>
                <Link
                    href="/login"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-500"
                >
                    Вход
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl font-bold text-slate-900">
                Политика конфиденциальности
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Последнее обновление: 4 июля 2026 г.
            </p>

            <div class="prose prose-slate mt-8 max-w-none text-sm leading-relaxed">
                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        1. Общие положения
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Настоящая политика описывает, как сервис
                        <strong>{{ appName }}</strong> (далее — «Сервис»),
                        доступный по адресу
                        <strong>https://crm.erlanpro.kg</strong>, обрабатывает
                        персональные данные пользователей и данные, получаемые
                        через интеграции с Meta (Facebook Messenger и Instagram
                        Direct).
                    </p>
                    <p class="mt-3 text-slate-600">
                        Оператор данных:
                        <strong>ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ</strong>
                        (сервис ErlanPro, https://crm.erlanpro.kg). ПИН:
                        21706199700221. По вопросам конфиденциальности:
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-indigo-600 hover:underline"
                            >{{ contactEmail }}</a
                        >.
                        <Link
                            href="/legal"
                            class="text-indigo-600 hover:underline"
                        >
                            Реквизиты ИП
                        </Link>.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        2. Какие данные мы обрабатываем
                    </h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                        <li>
                            Данные аккаунта CRM: имя, email, данные компании,
                            сотрудников и клиентов, введённые пользователем.
                        </li>
                        <li>
                            Сообщения и метаданные переписок из Instagram и
                            Facebook Messenger (имя отправителя, текст сообщения,
                            вложения, время отправки) — при подключении
                            интеграций Meta.
                        </li>
                        <li>
                            Технические данные: IP-адрес, cookies сессии,
                            журналы доступа к Сервису.
                        </li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        3. Цели обработки
                    </h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                        <li>
                            Предоставление функций CRM: учёт клиентов, сделок,
                            задач и переписки в едином интерфейсе.
                        </li>
                        <li>
                            Синхронизация и отправка сообщений через API Meta
                            от имени подключённых бизнес-аккаунтов.
                        </li>
                        <li>
                            Обеспечение безопасности, авторизации и технической
                            поддержки Сервиса.
                        </li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        4. Интеграция с Meta (Facebook / Instagram)
                    </h2>
                    <p class="mt-3 text-slate-600">
                        При подключении Instagram или Facebook Messenger
                        пользователь CRM авторизуется через Meta OAuth и
                        предоставляет Сервису доступ к выбранным Facebook-страницам
                        и связанным Instagram-аккаунтам. Мы используем полученные
                        маркеры доступа только для функций мессенджера в CRM и не
                        передаём их третьим лицам, за исключением случаев,
                        предусмотренных законом.
                    </p>
                    <p class="mt-3 text-slate-600">
                        Использование данных Meta также регулируется
                        <a
                            href="https://www.facebook.com/privacy/policy/"
                            class="text-indigo-600 hover:underline"
                            target="_blank"
                            rel="noopener noreferrer"
                            >политикой конфиденциальности Meta</a
                        >.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        5. Хранение и защита
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Данные хранятся на защищённых серверах. Доступ имеют только
                        авторизованные пользователи компании-владельца аккаунта CRM.
                        Маркеры доступа Meta хранятся в зашифрованном виде в базе
                        данных Сервиса.
                    </p>
                </section>

                <section id="data-deletion" class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        6. Удаление данных
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Вы можете запросить удаление своих данных или данных,
                        полученных через Meta:
                    </p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                        <li>
                            Отключите интеграцию Instagram/Facebook в разделе
                            «Интеграции» CRM — сохранённый маркер доступа будет
                            удалён.
                        </li>
                        <li>
                            Напишите на
                            <a
                                :href="`mailto:${contactEmail}`"
                                class="text-indigo-600 hover:underline"
                                >{{ contactEmail }}</a
                            >
                            с темой «Удаление данных» и укажите email аккаунта CRM
                            или идентификатор Facebook/Instagram. Мы обработаем
                            запрос в течение 30 календарных дней.
                        </li>
                        <li>
                            Вы также можете отозвать доступ приложения в настройках
                            Facebook: Настройки → Приложения и сайты.
                        </li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        7. Контакты
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Email:
                        <a
                            :href="`mailto:${contactEmail}`"
                            class="text-indigo-600 hover:underline"
                            >{{ contactEmail }}</a
                        ><br />
                        Сайт:
                        <a
                            href="https://crm.erlanpro.kg"
                            class="text-indigo-600 hover:underline"
                            >https://crm.erlanpro.kg</a
                        >
                    </p>
                </section>
            </div>
        </main>

        <PublicSiteFooter />
    </div>
</template>
