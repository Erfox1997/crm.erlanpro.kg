<script setup>
import PublicSiteFooter from '@/Components/PublicSiteFooter.vue';
import PublicSiteHeader from '@/Components/PublicSiteHeader.vue';
import { Head, Link } from '@inertiajs/vue3';

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

</script>

<template>
    <Head title="Пользовательское соглашение" />

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <PublicSiteHeader />

        <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
            <h1 class="text-2xl font-bold text-slate-900">
                Пользовательское соглашение
            </h1>
            <p class="mt-2 text-sm text-slate-500">
                Последнее обновление: 4 июля 2026 г.
            </p>

            <div class="prose prose-slate mt-8 max-w-none text-sm leading-relaxed">
                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        1. Предмет соглашения
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Настоящее Пользовательское соглашение (далее —
                        «Соглашение») регулирует использование сервиса
                        <strong>{{ appName }}</strong>, доступного по адресу
                        <strong>https://crm.erlanpro.kg</strong> (далее —
                        «Сервис»), предоставляемого
                        <strong>ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ</strong>
                        (торговое обозначение ErlanPro) (далее — «Оператор»).
                    </p>
                    <p class="mt-3 text-slate-600">
                        Регистрируясь в Сервисе или используя его функции, вы
                        подтверждаете, что ознакомились с Соглашением и принимаете
                        его условия.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        2. Описание Сервиса
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Сервис предназначен для управления клиентами, сделками,
                        воронками продаж и перепиской с клиентами через
                        интеграции с мессенджерами и социальными сетями (в том
                        числе Meta — Facebook Messenger и Instagram Direct).
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        3. Регистрация и аккаунт
                    </h2>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                        <li>
                            Для использования Сервиса необходима регистрация с
                            указанием достоверных данных.
                        </li>
                        <li>
                            Вы несёте ответственность за сохранность пароля и
                            все действия, совершённые под вашим аккаунтом.
                        </li>
                        <li>
                            Один аккаунт CRM привязан к одной компании. Доступ
                            сотрудников настраивается администратором компании.
                        </li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        4. Интеграции с Meta
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Подключая Instagram или Facebook Messenger, вы
                        авторизуете Сервис через Meta OAuth и предоставляете
                        доступ к выбранным бизнес-страницам и связанным
                        аккаунтам. Вы обязуетесь иметь законные права на
                        управление этими страницами и соблюдать
                        <a
                            href="https://www.facebook.com/policies_center/"
                            class="text-indigo-600 hover:underline"
                            target="_blank"
                            rel="noopener noreferrer"
                            >политики Meta</a
                        >.
                    </p>
                    <p class="mt-3 text-slate-600">
                        Оператор не несёт ответственности за ограничения или
                        блокировки со стороны Meta, вызванные нарушением правил
                        платформы.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        5. Права и обязанности пользователя
                    </h2>
                    <p class="mt-3 text-slate-600">Пользователь обязуется:</p>
                    <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                        <li>
                            не использовать Сервис для незаконной деятельности,
                            спама или рассылки без согласия получателей;
                        </li>
                        <li>
                            не пытаться получить несанкционированный доступ к
                            данным других компаний или пользователей;
                        </li>
                        <li>
                            соблюдать законодательство Кырgyzской Республики и
                            применимые нормы о персональных данных.
                        </li>
                    </ul>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        6. Тарифы и оплата
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Условия тарифов, лимиты и стоимость указываются в
                        интерфейсе Сервиса. Оператор вправе изменять тарифы с
                        предварительным уведомлением пользователей.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        7. Ограничение ответственности
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Сервис предоставляется «как есть». Оператор не
                        гарантирует бесперебойную работу и не несёт
                        ответственности за косвенные убытки, потерю данных или
                        упущенную выгоду, за исключением случаев, прямо
                        предусмотренных законом.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        8. Прекращение использования
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Вы можете прекратить использование Сервиса в любое
                        время, удалив аккаунт или отключив интеграции. Оператор
                        вправе приостановить или ограничить доступ при нарушении
                        Соглашения.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        9. Персональные данные
                    </h2>
                    <p class="mt-3 text-slate-600">
                        Обработка персональных данных описана в
                        <Link
                            href="/privacy"
                            class="text-indigo-600 hover:underline"
                            >Политике конфиденциальности</Link
                        >.
                    </p>
                </section>

                <section class="mb-8">
                    <h2 class="text-lg font-semibold text-slate-900">
                        10. Контакты
                    </h2>
                    <p class="mt-3 text-slate-600">
                        По вопросам Соглашения:
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
