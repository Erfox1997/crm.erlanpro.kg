<script setup>
/**
 * English Terms of Service body. Russian text remains authoritative.
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
            <h2 class="text-lg font-semibold text-slate-900">1. Subject of the agreement</h2>
            <p class="mt-3 text-slate-600">
                These Terms of Service (the “Agreement”) govern use of the
                <strong>{{ appName }}</strong> service available at
                <strong>https://crm.erlanpro.kg</strong> (the “Service”), provided by
                <strong>ИП АСАНАЛИЕВ ЭРЛАН МАЛИКОВИЧ</strong>
                (trade name ErlanPro) (the “Controller”).
            </p>
            <p class="mt-3 text-slate-600">
                By registering, accepting the terms, or actually using Service features, you
                confirm that you have read the Agreement and the
                <a href="/privacy" class="text-indigo-600 hover:underline">Privacy Policy</a>,
                accept them, and have authority to enter into the Agreement for yourself or
                the company you represent.
            </p>
            <p class="mt-3 text-slate-600">
                Controller details:
                <a href="/legal" class="text-indigo-600 hover:underline">“Sole proprietor details” page</a>.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">2. Description of the Service</h2>
            <p class="mt-3 text-slate-600">
                The Service is a multi-user CRM platform for managing clients, deals, sales
                funnels, tasks, and messaging via integrations with messengers and social
                networks (including Meta — Facebook Messenger and Instagram Direct, WhatsApp,
                Telegram), plus related features (broadcasts, Instagram comments, text
                improvement, sales via an external shop — when connections are available).
            </p>
            <p class="mt-3 text-slate-600">
                Available features may vary by plan and third-party platform availability. The
                Controller may develop, change, or temporarily limit features, notifying users
                of material changes.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">3. Registration and account</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>Using the Service requires registration with accurate information.</li>
                <li>
                    You are responsible for keeping your password secure and for all actions
                    under your account, including by employees you grant access to.
                </li>
                <li>
                    One CRM account is tied to one company. Employee access is configured by
                    the company admin (owner). The owner must promptly revoke access for former employees.
                </li>
                <li>
                    Sharing credentials with third parties outside your company, creating
                    accounts for others without authority, or bypassing plan limits via
                    multiple accounts without the Controller’s consent is prohibited.
                </li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">4. Roles and end-customer data</h2>
            <p class="mt-3 text-slate-600">
                The Client independently decides which end-customer, lead, and correspondent
                data to upload and process in the Service. The Controller provides the
                technical platform and processes such data on the Client’s behalf to deliver the services.
            </p>
            <p class="mt-3 text-slate-600">
                The Client warrants that it has a lawful basis to process end-customer personal
                data and to send them messages and broadcasts. The Client is fully responsible
                for message content, database accuracy, and compliance with personal data and advertising laws.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">5. Third-party platform integrations</h2>
            <p class="mt-3 text-slate-600">
                By connecting Instagram, Facebook Messenger, WhatsApp, Telegram, OpenAI, an
                external shop, or other integrations, you authorize the Service to access the
                relevant accounts/APIs and undertake to have lawful rights to manage them and
                to comply with platform rules (including
                <a
                    href="https://www.facebook.com/policies_center/"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >Meta policies</a
                >).
            </p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>API keys and tokens are provided by the Client; the Client is responsible for their safekeeping and correct access rights.</li>
                <li>
                    The Controller is not liable for limitations, blocks, API changes, or
                    unavailability of third-party platforms (Meta, WhatsApp, Telegram, OpenAI,
                    WhatsApp provider, Shop API, etc.).
                </li>
                <li>
                    The Controller may disable an integration or limit features if abuse, key
                    leakage, or violation of platform rules / this Agreement is suspected.
                </li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">6. User rights and obligations</h2>
            <p class="mt-3 text-slate-600">The user undertakes to:</p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>not use the Service for illegal activity, fraud, malware, spam, or messaging without recipient consent;</li>
                <li>not upload or transmit content that infringes third-party rights or the law;</li>
                <li>not attempt unauthorized access to other companies’ or users’ data, disrupt the Service, or run unauthorized penetration testing;</li>
                <li>not reverse-engineer the Service for purposes prohibited by law, or copy its structure/UI to build a competing product;</li>
                <li>comply with the laws of the Kyrgyz Republic and applicable personal data and advertising rules.</li>
            </ul>
            <p class="mt-3 text-slate-600">
                Upon recipient complaints, platform account blocks, or substantiated third-party
                claims, the Controller may suspend broadcasts and/or Service access until violations are remedied.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">7. Plans, payment, and refunds</h2>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>Plan terms (price, duration, employee limit, message retention, and other parameters) are shown in the Service UI.</li>
                <li>
                    Registration may include a trial on terms shown in the UI. After the trial,
                    access to paid features may be limited until a plan is paid.
                </li>
                <li>
                    Subscription payment is made outside an automatic payment gateway — using
                    the Controller’s details (bank transfer and/or other methods shown in the
                    payment section). The Client sends payment confirmation; the Controller
                    activates or renews the subscription after verifying funds within a reasonable time.
                </li>
                <li>
                    After the paid period ends, the Controller may limit or suspend Service
                    access until renewal. Company data may still be kept for a limited time
                    under the Privacy Policy.
                </li>
                <li>
                    <strong>Plan price changes.</strong> The Controller may change plan prices
                    at any time when needed. If the Client already paid (activated) a plan at a
                    given price, no extra payment is due until that period ends. After the
                    current period, renewal is at the price then in force. Example: a plan cost
                    1&nbsp;400 KGS/month, then changed to 1&nbsp;500 KGS/month — until the end
                    of the already paid month the Client pays nothing extra; the next month costs 1&nbsp;500 KGS.
                </li>
                <li>
                    <strong>Refunds.</strong> Refunds “without cause” are not provided. A refund
                    is possible only if the Service (CRM system) stopped working due to the
                    Controller’s fault and was not restored, or in other cases expressly required
                    by law. Send requests via Telegram
                    <a
                        :href="supportBotUrl"
                        class="text-indigo-600 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                        >{{ supportBotHandle }}</a
                    >
                    with company name and payment date.
                </li>
            </ul>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">8. Plan limits and message retention</h2>
            <p class="mt-3 text-slate-600">
                A plan may include an employee limit and messenger message retention period.
                Messages older than the plan period may be deleted automatically with no
                recovery. The Client must export and store important data outside the Service
                if a longer archive is needed.
            </p>
            <p class="mt-3 text-slate-600">
                If the employee limit is exceeded, the Controller may restrict adding new
                employees until usage matches the plan or the plan is changed.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">9. Intellectual property and Client content</h2>
            <p class="mt-3 text-slate-600">
                Exclusive rights to the Service (code, design, ErlanPro marks, documentation)
                belong to the Controller or its licensors. The Client receives a limited,
                non-exclusive, non-transferable right to use the Service during the subscription term.
            </p>
            <p class="mt-3 text-slate-600">
                The Client retains rights to data and content uploaded to the Service. The
                Client grants the Controller a license to process, store, and technically
                transmit such data solely to provide services under the Agreement.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">10. Availability, backup, and recovery</h2>
            <p class="mt-3 text-slate-600">
                The Service is provided as SaaS. The Controller aims to keep it operational but
                does not guarantee uninterrupted 24/7 availability. Maintenance, updates, and
                outages due to third-party platforms, networks, hosting equipment, and force
                majeure are allowed.
            </p>
            <p class="mt-3 text-slate-600">
                In case of a server failure or other infrastructure incident, the Controller
                will make reasonable efforts to restore the Service and data. At the time of
                publishing this Agreement, time-based backups/snapshots are used; a constantly
                synced replica is not yet deployed (planned). If full recovery is still
                impossible despite those measures, the Controller is not liable for data loss,
                lost profits, or other Client losses from such failure, to the extent permitted by law.
            </p>
            <p class="mt-3 text-slate-600">
                Clients are advised to regularly export critical information (contacts, deals, conversations) themselves.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">11. Feature change requests</h2>
            <p class="mt-3 text-slate-600">
                The Client may contact support to propose Service changes or additions. Not
                all requests will be implemented: the Controller reviews them based on overall
                benefit for most users. If a change helps only one Client and does not improve
                the Service overall, the Controller may refuse without obligation to implement
                or customize individually.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">12. Support access to the account</h2>
            <p class="mt-3 text-slate-600">
                If diagnosing or fixing an issue requires signing into the Client’s account,
                the Client may voluntarily provide the Controller (support staff) with login
                and password for the duration of the work. After the issue is resolved, the
                Client must change the password. The Controller uses provided credentials only
                for the stated support and is not obliged to keep the Client’s password after the work ends.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">13. Limitation of liability</h2>
            <p class="mt-3 text-slate-600">
                The Service is provided “as is” and “as available.” To the maximum extent
                permitted by KR law, the Controller is not liable for:
            </p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>indirect damages, lost profits, loss of clients, reputation, or data;</li>
                <li>server/hosting failures and cases where backup recovery is impossible or incomplete;</li>
                <li>failures and limits of third-party platforms (Meta, WhatsApp, Telegram, etc.), and blocks of the Client’s business accounts;</li>
                <li>actions or errors of the Client’s employees, or password/API key leaks caused by the Client;</li>
                <li>automatic message deletion under plan limits;</li>
                <li>content of the Client’s conversations and broadcasts.</li>
            </ul>
            <p class="mt-3 text-slate-600">
                The Controller’s aggregate liability for any claims related to the Agreement or
                Service is limited to the amount actually paid by the Client to the Controller
                for the Service in the last 3 (three) calendar months before the event giving
                rise to the claim (for a trial period — 0), unless law mandatorily provides otherwise.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">14. Ending use and data</h2>
            <p class="mt-3 text-slate-600">
                You may stop using the Service at any time by deleting the user account,
                disconnecting integrations, and/or requesting company data deletion via Telegram
                <a
                    :href="supportBotUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ supportBotHandle }}</a
                >.
            </p>
            <p class="mt-3 text-slate-600">
                The Controller may suspend or limit access for Agreement breach, non-payment,
                legal requirements, or to protect the Service and other users.
            </p>
            <p class="mt-3 text-slate-600">
                Before deletion or access end, export needed data yourself. On Client request,
                the Controller will within a reasonable time assist with exporting available
                company data where technically possible. After use ends, company data may be
                kept for a limited period (generally up to 30 calendar days unless otherwise
                agreed or required by law), then deleted or anonymized.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">15. Personal data</h2>
            <p class="mt-3 text-slate-600">
                Personal data processing is described in the
                <a href="/privacy" class="text-indigo-600 hover:underline">Privacy Policy</a>.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">16. Changes to the Agreement and policies</h2>
            <p class="mt-3 text-slate-600">
                Base Service rules are published on the site. Material policy changes are not
                made “quietly”: for each such change the Controller publishes a notice at the same time:
            </p>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-slate-600">
                <li>
                    on the site — on
                    <a href="/updates" class="text-indigo-600 hover:underline">/updates</a>
                    (“Policy updates”) and, if needed, in the Agreement / Policy text;
                </li>
                <li>
                    in the Telegram news group
                    <a
                        :href="newsGroupUrl"
                        class="text-indigo-600 hover:underline"
                        target="_blank"
                        rel="noopener noreferrer"
                        >“ErlanPro News”</a
                    >
                    — via a bot message. Clients may join the group via the link to learn about
                    changes. The publication date and time are fixed by the Telegram message
                    itself (the message date cannot be arbitrarily backdated), ensuring transparency.
                </li>
            </ul>
            <p class="mt-3 text-slate-600">
                Continued use after changes are published means acceptance. If the Client
                disagrees, they may stop using the Service and request company data deletion.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">17. Governing law and disputes</h2>
            <p class="mt-3 text-slate-600">
                The Agreement is governed by the laws of the Kyrgyz Republic. Parties seek to
                resolve disputes by negotiation. Before going to court, the Client sends the
                Controller a written claim via Telegram
                <a
                    :href="supportBotUrl"
                    class="text-indigo-600 hover:underline"
                    target="_blank"
                    rel="noopener noreferrer"
                    >{{ supportBotHandle }}</a
                >.
                Claim review period is 15 calendar days from receipt, unless otherwise agreed.
            </p>
            <p class="mt-3 text-slate-600">
                If no agreement is reached, the dispute is heard in court at the Controller’s
                place of registration under KR law, unless law mandatorily provides otherwise.
            </p>
        </section>

        <section class="mb-8">
            <h2 class="text-lg font-semibold text-slate-900">18. Contacts</h2>
            <p class="mt-3 text-slate-600">
                For Agreement and support questions — Telegram:
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
