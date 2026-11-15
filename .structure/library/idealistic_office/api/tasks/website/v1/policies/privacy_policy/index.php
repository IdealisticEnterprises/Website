<?php
require '/var/www/.structure/library/base/utilities.php';
require '/var/www/.structure/library/idealistic_office/init.php';
IdealisticOfficeBrand::start();
$text = ('PRIVACY POLICY
Effective Date: 8th of October 2026
Software: ' . IdealisticOfficeVariable::APPLICATION_NAME . '
Contact: contact@idealistic.ai
Website: https://www.idealistic.ai
Version Number: 5

At Idealistic OÜ ("we", "us", "our"), we are committed to protecting your privacy and handling your personal data in accordance with the General Data Protection Regulation (EU) 2016/679 ("GDPR").

ABOUT THE SERVICE
' . IdealisticOfficeVariable::APPLICATION_NAME . ' is a way for humans to communicate harmoniously with AI. People talk to each other as usual in the chats they already use, ' . IdealisticOfficeVariable::APPLICATION_NAME . ' keeps track of the conversation, and the AI only responds when a human asks it to. ' . IdealisticOfficeVariable::APPLICATION_NAME . ' is deployed on Discord, Telegram, WhatsApp and as a chat widget on websites. It can be configured from all of those, including in direct messages on Discord, Telegram and WhatsApp, and also from Messenger, Instagram, email and the iOS, Android and web apps. On the web, logged-in users will be able to join others on our website. ' . IdealisticOfficeVariable::APPLICATION_NAME . ' is a European registered product of Idealistic OÜ, a company registered in Estonia, in the European Union (https://ariregister.rik.ee/eng/company/17320016/Idealistic-OÜ), and ' . IdealisticOfficeVariable::APPLICATION_NAME . ' is European Union trademark no. 019353308 (https://euipo.europa.eu/eSearch/#details/trademarks/019353308). Parts of ' . IdealisticOfficeVariable::APPLICATION_NAME . ' are open source and published at https://github.com/IdealisticAI.

1. DATA WE COLLECT
To keep track of conversations and answer when asked, the Service ingests, processes, and structures various data types. Depending on your usage, this may include:
- Identity & Authentication Data: Information required to identify users and assign permissions (e.g., names, display names, usernames, email addresses, platform-specific unique identifiers, digital signatures, and IP addresses for consent logging).
- Conversation Data: Where ' . IdealisticOfficeVariable::APPLICATION_NAME . ' is installed in a chat, group, channel, thread, topic or website widget, it receives the messages sent there so it can keep track of the conversation. This includes text, audio recordings, file uploads, links and commands. Messages that ask ' . IdealisticOfficeVariable::APPLICATION_NAME . ' something (for example by tagging it) are answered. All other messages are kept as history and are not answered.
- Workspace & Configuration Data: Portals and their installation details (e.g., server, channel, group or topic identifiers), settings, language and time zone choices, roles and permissions.
- Subscription Data: Your plan, its status, credits, and billing identifiers. Payments are handled by our payment provider, Paddle. We receive subscription and billing information, not your full payment card details.
- Telemetry & Technical Data: System-generated data necessary for security and optimization, including IP addresses, timestamps, device profiles, and interaction logs.

2. GROUP CHATS AND SHARED SPACES
' . IdealisticOfficeVariable::APPLICATION_NAME . ' works inside conversations that involve several people. When someone installs ' . IdealisticOfficeVariable::APPLICATION_NAME . ' in a chat, group, channel or on a website, the messages of everyone taking part in that space are processed so the conversation can be tracked. Whoever installs ' . IdealisticOfficeVariable::APPLICATION_NAME . ' is responsible for making sure the other participants are aware of this. Any participant can exercise their rights described in this policy by contacting privacy@idealistic.ai.

3. DATA LIFECYCLE ARCHITECTURE
To provide absolute transparency into our data governance, the lifecycle of user data from ingestion to deletion is strictly mapped as follows:

a. Ingestion, Extraction & Storage
- What is Stored: Raw multimodal inputs (including text messages, audio recordings, and file uploads) are ingested and securely stored within our databases. The Service retains both the raw input and the resulting outcomes to maintain conversation context and historical continuity.
- What is Extracted: Our AI models parse the raw input to extract intent (e.g., what was asked, decided or promised). The original message and associated file assets remain securely stored and retrievable by the system.

b. Hosting & Access
- Infrastructure Provider: All raw conversational data, uploaded files, and configuration data are hosted on secure, dedicated servers provided by Hetzner, located entirely within the European Economic Area (EEA).
- Access Controls: Data is strictly compartmentalized. Access is limited to (1) the automated processing systems of the Service, (2) authorized administrators of the portal or workspace in question, and (3) restricted internal engineering personnel solely for security audits or critical debugging.

c. Retention & Deletion (Right to Erasure)
- Retention Period: We keep information for 30 days at minimum, which is required for legal reasons, and for 90 days at maximum. Users can choose a retention period between 31 and 90 days as an option.
- No Permanent Retention: When the retention period ends, information is deleted. Information is never retained permanently.
- Erasure Requests: If you ask for your data to be erased before the retention period ends, all Personally Identifiable Information (PII), including names, contact details, and platform identifiers, is permanently ANONYMIZED in our active systems, and the remaining records are deleted when the retention period ends.
- Backups: Data securely held in system backups is subject to a strict 7-day automated rolling overwrite cycle. Once information is deleted or anonymized in our primary systems, all associated identifiable data is completely purged from all disaster recovery backups within a maximum of 7 days.
- No Analysis, No Selling: We do not analyze your data beyond providing the Service, and we do not sell it. We are not in that business. Sending a message to our AI provider (see section 6) so that a reply can be produced when you ask is part of providing the Service, not analysis or selling.

4. PURPOSES OF DATA PROCESSING
We process your personal data to:
- Provide the Service: Keep track of conversations, answer when a human asks, and manage portals, access and subscriptions.
- AI & Multimodal Processing: Analyze varied inputs to extract intent and generate relevant answers when asked.
- Security & Logs: Maintain the integrity of our systems, prevent abuse, and log definitive consent events.
- Support: Respond to the messages you send us.
- Compliance: Fulfill legal, tax, and contractual obligations.

5. LEGAL BASIS
We process personal data based on:
- Contractual Necessity: To deliver the capabilities defined in our Terms of Use.
- Legitimate Interests: To ensure network security and maintain the historical record of conversations in the spaces where ' . IdealisticOfficeVariable::APPLICATION_NAME . ' is installed.
- Consent: Where required, for example when you accept our Terms of Use or choose a specific setting.
- Legal Obligations: Compliance with statutory frameworks.

6. THIRD-PARTY PLATFORMS & DATA SHARING
Crucial Notice regarding Transit Infrastructure:
Our service operates via third-party communication interfaces (including but not limited to Discord, Telegram, WhatsApp, Messenger, Instagram and email providers) and, through our chat widget, on websites run by third parties. By utilizing our service through these platforms, you acknowledge that data transmission is subject to the privacy policies and infrastructure of those providers and website owners.

- We do NOT sell your personal data, and we do NOT analyze it beyond providing the Service. We are not in that business.
- Infrastructure, Payment & Security Subprocessors: We share data with trusted providers strictly for the purpose of hosting, securing, operating and billing the application. Our authorized subprocessors include Hetzner (primary database hosting and storage), Cloudflare (edge routing, content delivery, and web application security), Google (internal routing, workspace infrastructure, and telemetry), and Paddle (payments and subscription management).
- AI Processing via OpenAI: We utilize OpenAI\'s enterprise API for our core extraction and generative capabilities. Under OpenAI\'s API policies, your conversational data is explicitly opted out of model training; your inputs are NEVER used to train their foundational models. Data transmitted to OpenAI is subject to their strict API data retention limits (retained for a maximum of 30 days solely for security and abuse monitoring before being permanently deleted from their servers).

7. OPEN SOURCE
Parts of ' . IdealisticOfficeVariable::APPLICATION_NAME . ', such as the Discord, Telegram, Android and iOS apps, are open source and published at https://github.com/IdealisticAI. Anyone can read how those apps work.

8. YOUR RIGHTS
Under the GDPR, you have the right to:
- Access & Portability: Request a copy of your personal data.
- Rectification: Correct inaccurate data.
- Erasure (Right to be Forgotten): Request the anonymization of your personal identity from our active systems, or deletion when the retention period described in section 3c ends.
- Restriction & Objection: Object to specific processing activities.

To exercise these rights, please contact our Data Protection Office at: privacy@idealistic.ai

9. DATA SECURITY
We use appropriate technical and organizational measures (including enterprise-grade encryption, edge-network firewalls, and one-time token authentication) to safeguard your personal data. However, we cannot guarantee the security of data while it is being transmitted through third-party messaging apps or external transit infrastructure prior to reaching our secured networks.

10. INTERNATIONAL DATA TRANSFERS
Our primary processing and storage servers (Hetzner) are located within the European Economic Area (EEA). However, by utilizing global edge networks (Cloudflare), enterprise tools (Google), payment providers (Paddle), or specific third-party transit platforms, some telemetry, routing, billing or conversational data may be processed outside the EEA (e.g., in the United States). Where such international transfers occur, we ensure compliance with the GDPR by relying on legally approved transfer mechanisms, including the EU-US Data Privacy Framework (DPF) and Standard Contractual Clauses (SCCs), to ensure your data rights remain fully protected.

11. CHANGES TO THIS POLICY
We may update this privacy policy periodically to reflect evolving capabilities, subprocessor additions, or legal requirements. Material changes will be communicated via our interfaces, and continued use will require explicit consent.

12. CONTACT US
Idealistic OÜ
Järvevana tee 9, Tallinn, 11314, Estonia
Privacy & GDPR Inquiries: privacy@idealistic.ai
Support & General Contact: contact@idealistic.ai (support is answered by humans, so delays may apply)
Business & Enterprise: enterprises@idealistic.ai');

IdealisticOfficeLegal::render($text, IdealisticOfficeVariable::PRIVACY_POLICY_URL);
