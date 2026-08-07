<script setup>
/**
 * English Privacy Policy body. Russian text remains authoritative.
 */
defineProps({
    appName: { type: String, required: true },
    supportBotHandle: { type: String, required: true },
    supportBotUrl: { type: String, required: true },
    newsGroupUrl: { type: String, required: true },
});
</script>

<template>
    <div class="prose prose-slate mt-8 max-w-none text-sm leading-relaxed">
        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">1. General provisions</h2>
            <p class="mt-3 text-slate-600">
                This policy describes how the
                <strong>{{ appName }}</strong> service (the “Service”), available at
                <strong>https://crm.erlanpro.kg</strong>, processes personal and other
                user data and data received through messenger and third-party integrations.
            </p>
            <p class="mt-3 text-slate-600">
                CRM account data controller:
                <strong>ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ</strong>
                (ErlanPro service, https://crm.erlanpro.kg). PIN: 21706199700221.
                For privacy, data deletion, and related issues, contact Telegram:
                <a
                    :href="supportBotUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ supportBotHandle }}</a
                >.
                <a href="/legal" class="text-indigo-600 hover:underline">Sole proprietor details</a>.
            </p>
            <p class="mt-3 text-slate-600">
                By using the Service, you confirm that you have read this Policy. Data
                processing is also governed by the
                <a href="/terms" class="text-indigo-600 hover:underline">Terms of Service</a>.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">2. Roles of the parties</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>
                    The <strong>Controller</strong> independently determines the purposes and
                    means of processing CRM account data (registration, authorization, plans,
                    support, security).
                </li>
                <li>
                    The <strong>Client (company)</strong> using the Service independently
                    determines the purposes of processing its end customers’, leads’, and
                    messenger correspondents’ data. For such data, the Controller acts as a
                    processor on the Client’s behalf, to the extent needed to provide the Service.
                </li>
                <li>
                    The Client is responsible for the lawfulness of collecting and processing
                    end-customer data, obtaining required consents (including for broadcasts),
                    and message content.
                </li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">3. What data we process</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>
                    CRM account data: name, email, password (hashed), company data, employees
                    (including roles and Telegram username if provided), clients and deals
                    entered by the user, including custom fields.
                </li>
                <li>
                    Messenger data: messages and conversation metadata (participant
                    name/ID, text, attachments, time, read status, manager assignment) from
                    Instagram Direct, Facebook Messenger, WhatsApp, Telegram — when the
                    relevant integrations are connected.
                </li>
                <li>
                    Instagram post comments: comment text, author, time — when that feature is used.
                </li>
                <li>
                    Integration data: access tokens and API keys (Meta, Telegram,
                    WhatsApp/Wappi, OpenAI, external shop, etc.) stored encrypted; connection metadata.
                </li>
                <li>
                    Shop/sales data: created sales and drafts sent to an external Shop API when connected.
                </li>
                <li>
                    Payment information for subscription activation: payment confirmations
                    sent by the Client to the Controller (card data is not processed in the
                    Service — payment is outside a payment gateway).
                </li>
                <li>
                    Technical data: IP address, session cookies, access logs, device/browser
                    information as needed for operation and security.
                </li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">4. Processing purposes</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>Providing CRM features: clients, deals, tasks, funnels, and messaging in one interface.</li>
                <li>Syncing, receiving, and sending messages via third-party APIs on behalf of the Client’s connected business accounts.</li>
                <li>Bulk and scheduled broadcasts under rules set by the Client.</li>
                <li>Improving outgoing message text via OpenAI (ChatGPT) if the Client connected its API key.</li>
                <li>Creating sales and sending data to the Client’s external shop (Shop API).</li>
                <li>Subscription, plan, and limit accounting; contacting the Client about payment and support.</li>
                <li>Security, authorization, abuse prevention, and technical support.</li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">5. Third-party integrations</h2>
            <p class="mt-3 text-slate-600">
                To run Service features, data may be transferred to (or received from) these categories of recipients:
            </p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>
                    <strong>Meta (Facebook / Instagram)</strong> — OAuth, Direct/Messenger
                    messages, comments. Also governed by
                    <a
                        href="https://www.facebook.com/privacy/policy/"
                        class="text-indigo-600 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                        >Meta’s privacy policy</a
                    >.
                </li>
                <li><strong>WhatsApp</strong> — via Wappi or another provider set in integration settings.</li>
                <li><strong>Telegram</strong> — Bot API (company bots and the managers bot/Mini App).</li>
                <li><strong>OpenAI</strong> — message text processing when text improvement is enabled (using the Client’s key).</li>
                <li><strong>External Shop API</strong> — catalog, sales, receipts — via URL and token set by the Client.</li>
                <li><strong>Hosting, email, infrastructure</strong> — hosting the Service and sending system emails (verification, password reset, etc.).</li>
            </ul>
            <p class="mt-3 text-slate-600">
                When connecting Instagram or Facebook Messenger, the CRM user authorizes via
                Meta OAuth and grants access to selected pages and linked Instagram accounts.
                Access tokens are used only for Service features and are not sold to third parties.
            </p>
            <p class="mt-3 text-slate-600">
                The Client configures tokens and access rights to third-party services and is
                responsible for their safekeeping and compliance with platform rules.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">6. Cookies and similar technologies</h2>
            <p class="mt-3 text-slate-600">
                The Service uses necessary cookies and session data for authorization, keeping
                you signed in, and abuse protection. Disabling such cookies may make the account
                area unusable. Analytical or advertising third-party trackers, if added later,
                will be described in an updated version of this Policy.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">7. Retention periods</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>
                    Account and company data are stored while the agreement (use of the Service)
                    is in force and for periods needed to meet obligations, resolve disputes, and comply with law.
                </li>
                <li>
                    Messenger messages are stored within the period set by the company’s plan
                    (e.g. 30, 90, 180, or 365 days; some plans may be unlimited). Messages older
                    than the plan limit may be deleted automatically.
                </li>
                <li>
                    Integration access tokens are stored while the integration is connected and
                    are deleted or invalidated when disconnected.
                </li>
                <li>
                    Technical logs are kept for a limited time needed for security and diagnostics.
                </li>
            </ul>
            <p class="mt-3 text-slate-600">
                Current retention limits by plan are shown in the Service UI and may change when the plan changes.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">8. Storage and security</h2>
            <p class="mt-3 text-slate-600">
                Data is stored on protected servers. Access to company data is granted to
                authorized users of that company according to roles and permissions, and to
                authorized Controller staff as needed for support and operation. Access tokens
                and API keys are stored encrypted. Data is transmitted over protected channels
                (HTTPS) where applicable.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">9. Data subject rights</h2>
            <p class="mt-3 text-slate-600">
                Regarding CRM account data, you may request clarification, correction,
                restriction, or deletion — via profile settings or by writing to the support bot
                <a
                    :href="supportBotUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ supportBotHandle }}</a
                >.
            </p>
            <p class="mt-3 text-slate-600">
                Requests from a company’s end customers (buyers, messenger subscribers) about
                access or deletion should be sent to the Client — the owner of that database.
                The Controller assists the Client in fulfilling such requests within a reasonable
                time where technically possible.
            </p>
        </section>

        <section id="data-deletion" class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">10. Data deletion</h2>
            <p class="mt-3 text-slate-600">
                You may request deletion of your data or data obtained through integrations:
            </p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>
                    Disconnect the integration under CRM “Integrations” — the stored access
                    token will be deleted or anonymized.
                </li>
                <li>
                    Write to Telegram
                    <a
                        :href="supportBotUrl"
                        class="text-indigo-600 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ supportBotHandle }}</a
                    >
                    with the text “Data deletion” and your CRM account email and/or
                    Facebook/Instagram/Telegram ID. We will process the request within 30
                    calendar days unless law or Meta requires otherwise.
                </li>
                <li>
                    You may revoke the app’s access in Facebook settings: Settings → Apps and websites.
                </li>
                <li>
                    Deleting a user account does not always mean immediate deletion of all
                    company data (deals, conversations, employees) — for full company data
                    deletion, write to
                    <a
                        :href="supportBotUrl"
                        class="text-indigo-600 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ supportBotHandle }}</a
                    >.
                    After you stop using the Service, company data may be kept for a limited
                    time to finish settlements and upon Client request, then deleted or
                    anonymized unless law requires otherwise.
                </li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">11. Children</h2>
            <p class="mt-3 text-slate-600">
                The Service is intended for entrepreneurs and organizations and is not directed
                at persons under 18. We do not knowingly collect children’s personal data. If
                you learn of such processing, write to Telegram
                <a
                    :href="supportBotUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ supportBotHandle }}</a
                >.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">12. Policy changes</h2>
            <p class="mt-3 text-slate-600">
                The Controller may update this Policy. The current version is published on this
                page. Material changes are also published on
                <a href="/updates" class="text-indigo-600 hover:underline">/updates</a>
                and in the Telegram news group
                <a
                    :href="newsGroupUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >“ErlanPro News”</a
                >
                — the notice date is recorded by the Telegram message. You may join the group
                via the link above to follow changes.
            </p>
            <p class="mt-3 text-slate-600">
                Continued use of the Service after changes are published means acceptance of
                the updated Policy, unless law requires otherwise.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">13. Contacts</h2>
            <p class="mt-3 text-slate-600">
                Support and issues — Telegram:
                <a
                    :href="supportBotUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ supportBotHandle }}</a
                ><br />
                News and policy changes — group:
                <a
                    :href="newsGroupUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >ErlanPro News</a
                ><br />
                Website:
                <a href="https://crm.erlanpro.kg" class="text-indigo-600 hover:underline"
                    >https://crm.erlanpro.kg</a
                >
            </p>
        </section>
    </div>
</template>
