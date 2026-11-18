<?php

namespace idealistic_office\api;

use account\api\AccountVariables;
use account\api\objects\correlation\AccountTeam;
use account\api\objects\security\AccountSession;
use idealistic_office\api\handlers\IdealisticOfficeError;
use idealistic_office\api\objects\helpers\AbstractIdealisticOfficeTeam;
use idealistic_office\api\objects\helpers\attachment\AbstractIdealisticOfficeAttachment;

class IdealisticOfficeVariable
{

    public const
        EMBEDDING_MODEL = AIModelFamily::OPENAI_EMBEDDING_LARGE,
        EMBEDDING_PROBABILITY_MINIMUM_THRESHOLD = 0.1,
        TASK_AI_CHEAP_MODEL = AIModelFamily::CHAT_GPT_NANO,
        TASK_AI_BALANCED_MODEL = AIModelFamily::CHAT_GPT,
        TASK_AI_EXPENSIVE_MODEL = AIModelFamily::CHAT_GPT_PRO,
        TASK_AI_DOCUMENT_READING_MODEL = self::TASK_AI_CHEAP_MODEL,
        TASK_AI_IMAGE_READING_MODEL = self::TASK_AI_CHEAP_MODEL,
        TASK_AI_IMAGE_CREATING_MODEL = AIModelFamily::GPT_IMAGE,
        TASK_AI_VOICE_READING_MODEL = AIModelFamily::OPENAI_SPEECH_PRO,
        TASK_AI_VOICE_CREATING_MODEL = AIModelFamily::OPENAI_SPEECH_TEXT,
        TASK_AI_LINK_ANALYSIS_MODEL = self::TASK_AI_CHEAP_MODEL,
        TASK_AI_NO_REASONING_EFFORT = "none",
        TASK_AI_DECREASED_REASONING_EFFORT = "low",
        TASK_AI_DEFAULT_REASONING_EFFORT = "medium",
        TASK_AI_INCREASED_REASONING_EFFORT = "high",
        TASK_AI_LOW_VERBOSITY = "low",
        TASK_AI_MEDIUM_VERBOSITY = "medium",
        TASK_AI_MAX_VERBOSITY = "high",
        DEFAULT_TASK_SUFFIX = "CONFIRMATION:",
        COMPANY_NAME = "Idealistic OÜ",
        COMPANY_DETAILS = "https://ariregister.rik.ee/eng/company/17320016/Idealistic-OÜ",
        CAPTCHA_SITE_KEY = "6Lf_zyQUAAAAAAxfpHY5Io2l23ay3lSWgRzi_l6B",
        APPLICATION_NAME = "iDEALISTIC®",
        PADDLE_CLIENT_TOKEN = "live_70322f80b2ae7f8e648ea587754",
        EMAIL_DOMAIN = "@idealistic.ai",
        SUPPORT_EMAIL = "contact" . self::EMAIL_DOMAIN,
        REPLY_EMAIL = "beepboop" . self::EMAIL_DOMAIN,
        COMPANY_WEBSITE_URL = "https://www.idealistic.ai",
        PORTAL_DEFAULT_FOR_TESTING = "ctiqNEfqw5Ul3lL7",
        PORTAL_EXTERNAL_SCRIPT_SCRIPT_ID = "idealistic-script",
        PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER = "YOUR_DESIRED_PORTAL_ID",
        PORTAL_EXTERNAL_SCRIPT_DEFAULT_MESSAGE_PLACEHOLDER = "YOUR_DESIRED_DEFAULT_MESSAGE",
        PORTAL_EXTERNAL_SCRIPT_DEFAULT_DOMAIN_PLACEHOLDER = "YOUR_DESIRED_DEFAULT_DOMAIN",
        PORTAL_EXTERNAL_SCRIPT = "<script src='https://www.idealistic.ai/.scripts/v1/portal.js' data-portal='" . self::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER . "' id='idealistic-script' defer></script>",
        PORTAL_EXTERNAL_SCRIPT_WITH_DEFAULT_MESSAGE = "<script src='https://www.idealistic.ai/.scripts/v1/portal.js' data-portal='" . self::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER . "' data-default-message='" . self::PORTAL_EXTERNAL_SCRIPT_DEFAULT_MESSAGE_PLACEHOLDER . "' id='" . self::PORTAL_EXTERNAL_SCRIPT_SCRIPT_ID . "' defer></script>",
        PORTAL_EXTERNAL_SCRIPT_WITH_DEFAULT_DOMAIN = "<script src='https://www.idealistic.ai/.scripts/v1/portal.js' data-portal='" . self::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER . "' data-default-domain='" . self::PORTAL_EXTERNAL_SCRIPT_DEFAULT_DOMAIN_PLACEHOLDER . "' id='" . self::PORTAL_EXTERNAL_SCRIPT_SCRIPT_ID . "' defer></script>",
        PORTAL_EXTERNAL_SCRIPT_WITH_DEFAULT_MESSAGE_AND_DOMAIN = "<script src='https://www.idealistic.ai/.scripts/v1/portal.js' data-portal='" . self::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER . "' data-default-message='" . self::PORTAL_EXTERNAL_SCRIPT_DEFAULT_MESSAGE_PLACEHOLDER . "' data-default-domain='" . self::PORTAL_EXTERNAL_SCRIPT_DEFAULT_DOMAIN_PLACEHOLDER . "' id='" . self::PORTAL_EXTERNAL_SCRIPT_SCRIPT_ID . "' defer></script>",
        TERMS_OF_USE_URL = self::COMPANY_WEBSITE_URL . "/terms/terms_of_use/",
        PRIVACY_POLICY_URL = self::COMPANY_WEBSITE_URL . "/policies/privacy_policy/",
        USER_DATA_DELETION_INSTRUCTIONS_URL = self::COMPANY_WEBSITE_URL . "/instructions/user_data_deletion/",
        INSTAGRAM_APP_URL = self::COMPANY_WEBSITE_URL . "/instagram/",
        MESSENGER_APP_URL = self::COMPANY_WEBSITE_URL . "/messenger/",
        WHATSAPP_APP_URL = self::COMPANY_WEBSITE_URL . "/whatsapp/",
        DISCORD_APP_URL = self::COMPANY_WEBSITE_URL . "/discord/",
        TELEGRAM_APP_URL = self::COMPANY_WEBSITE_URL . "/telegram/",
        ANDROID_APP_URL = self::COMPANY_WEBSITE_URL . "/android/",
        IOS_APP_URL = self::COMPANY_WEBSITE_URL . "/iOS/",
        IPADOS_APP_URL = self::COMPANY_WEBSITE_URL . "/iPadOS/",
        DOWNLOAD_URL = self::COMPANY_WEBSITE_URL . "/download",
        WORK_URL = self::COMPANY_WEBSITE_URL . "/work",
        ANDROID_STORE_URL = "https://play.google.com/store/apps/details?id=ai.idealistic",
        IOS_STORE_URL = "https://apps.apple.com/us/app/idealistic/id6797969942",
        INPUT_OUTPUT_URL = self::COMPANY_WEBSITE_URL . "/io",
        ACCOUNT_INPUT_OUTPUT_URL = self::INPUT_OUTPUT_URL . "/v1/account",
        ACCOUNT_SIGN_IN_INPUT_OUTPUT_URL = self::ACCOUNT_INPUT_OUTPUT_URL . "/signin",
        ACCOUNT_DOWNLOAD_INPUT_OUTPUT_URL = self::ACCOUNT_INPUT_OUTPUT_URL . "/download",
        LEGAL_INPUT_OUTPUT_URL = self::INPUT_OUTPUT_URL . "/v1/legal",
        LEGAL_SIGN_INPUT_OUTPUT_URL = self::LEGAL_INPUT_OUTPUT_URL . "/sign",
        PORTAL_INPUT_OUTPUT_URL = self::INPUT_OUTPUT_URL . "/v1/portal",
        PORTAL_VIEW_INPUT_OUTPUT_URL = self::PORTAL_INPUT_OUTPUT_URL . "/view",
        PORTAL_ADD_INPUT_OUTPUT_URL = self::PORTAL_INPUT_OUTPUT_URL . "/add",
        ATTACHMENT_INPUT_OUTPUT_URL = self::INPUT_OUTPUT_URL . "/v1/attachment",
        ATTACHMENT_VIEW_INPUT_OUTPUT_URL = self::ATTACHMENT_INPUT_OUTPUT_URL . "/view",
        ATTACHMENT_DOWNLOAD_INPUT_OUTPUT_URL = self::ATTACHMENT_INPUT_OUTPUT_URL . "/download",
        CHAT_PORTAL_DEFAULT_NAME = "default",
        AI_HASH = 198203537,
        DEFAULT_SESSION_CHECK_IP_ADDRESS = false,
        DEFAULT_TIME_ZONE = "UTC",
        DEFAULT_DATE_FORMAT = "Y-m-d H:i:s",
        DEFAULT_LANGUAGE = "english",
        HISTORY_PROMPT_SECONDS_DELAY_NOTIFICATION = 7,
        RECENT_PADDLE_ACCOUNT_SECONDS = 60 * 60 * 24 * 3,
        DEFAULT_CURRENCY_ID = 1, // EUR
        CURRENCY_USAGE_FOR_UNKNOWN_AI_COST = 0.001,
        MINIMUM_CREDITS_TO_USE = 0.2,
        MINIMUM_PORTAL_CREDITS_TO_USE = self::MINIMUM_CREDITS_TO_USE / 10,
        NEW_TEAM_DEFAULT_CREDITS = (1.0 / IdealisticOfficePlans::MAXIMUM_EXPECTED_PRICE_PER_APP_MESSAGE) * self::MINIMUM_CREDITS_TO_USE,
        BUSINESS_EMAIL = "enterprises" . self::EMAIL_DOMAIN,
        WORK_EMAIL = "work" . self::EMAIL_DOMAIN,
        COMPANY_ADDRESS = "Järvevana tee 9, Tallinn, 11314, Estonia",
        COMPANY_COUNTRY = "Estonia",
        TRADEMARK_NUMBER = "019353308",
        TRADEMARK_URL = "https://euipo.europa.eu/eSearch/#details/trademarks/019353308",
        GITHUB_URL = "https://github.com/IdealisticAI",
        YOUTUBE_URL = "https://www.youtube.com/@IdealisticAI",
        SPARTAN_URL = "https://spartan.top",
        PORTAL_CHAT_URL = self::COMPANY_WEBSITE_URL . "/chat",
        PORTAL_STRUCTURE_URL = self::COMPANY_WEBSITE_URL . "/structure",
        SUPPORT_RESPONSE_NOTE = "Support is answered by humans, so delays may apply.",
        DATA_RETENTION_MINIMUM_DAYS = 30,
        DATA_RETENTION_OPTION_MINIMUM_DAYS = 31,
        DATA_RETENTION_MAXIMUM_DAYS = 90,
        DEFAULT_TEXT_SEPARATOR = ", ";
}

class IdealisticOfficeWebsite
{
    public const
        TITLE = (IdealisticOfficeVariable::APPLICATION_NAME . " | Where humans and AI talk in harmony"),
        TAGLINE = "Where humans and AI talk in harmony",
        DESCRIPTION = ("Add " . IdealisticOfficeVariable::APPLICATION_NAME . " to Discord, Telegram, WhatsApp or your website. Humans talk, AI keeps track and only speaks when asked. Settle disputes, resolve disagreements and bring chats back to life."),
        COMING_SOON = true,
        COMING_SOON_LABEL = "Coming soon",
        COMING_SOON_NOTE = (IdealisticOfficeVariable::APPLICATION_NAME . " has not launched yet. Plans and payments open at launch. You can try the demo in the meantime."),
        COMING_SOON_PRICING_TITLE = "Plans open at launch.",
        HERO_LEAD = (IdealisticOfficeVariable::APPLICATION_NAME . " is the way for people and AI to communicate in harmony. Add it to the chats you already have. Everyone keeps talking, the AI quietly keeps track, and it only speaks when a human asks."),
        HUMAN_MADE_TITLE = "Human design. Human backend.",
        TRANSLATE_NOTE = "This website is in English. To read it in your language, use your browser's translate feature.",
        AI_MODELS_NOTE = (IdealisticOfficeVariable::APPLICATION_NAME . " is based on ChatGPT models from OpenAI. It is not affiliated with or endorsed by OpenAI."),
        HUMAN_MADE_TEXT = (IdealisticOfficeVariable::APPLICATION_NAME . " is built by humans at ") . IdealisticOfficeVariable::COMPANY_NAME . ". " . self::AI_MODELS_NOTE . (" The AI inside " . IdealisticOfficeVariable::APPLICATION_NAME . " never starts a conversation, and it only answers when one of us asks."),
        WORK_NAV = "Work with us",
        WORK_TITLE = ("Work at " . IdealisticOfficeVariable::APPLICATION_NAME),
        WORK_DESCRIPTION = ("Join the humans building " . IdealisticOfficeVariable::APPLICATION_NAME . ". We mostly look for engineers and email support. To apply, send an email to " . IdealisticOfficeVariable::WORK_EMAIL . "."),
        WORK_EYEBROW = "Work with us",
        WORK_LEAD = "We are a small team of humans, and we mostly look for engineers and people for email support. If that sounds like you, write to us.",
        WORK_ROLES_LABEL = "Who we look for",
        WORK_ROLES_TITLE = "Engineers and email support.",
        WORK_ROLES = array(
        array("title" => "Engineers", "text" => "Build the platform behind every conversation: the website, the Discord, Telegram and WhatsApp integrations, and the Android and iOS apps."),
        array("title" => "Email support", "text" => "Answer the people who write to us. Support is handled by humans, so clear, kind and careful writing in English matters most.")
    ),
        WORK_APPLY_LABEL = "How to apply",
        WORK_APPLY_TITLE = "Send us an email.",
        WORK_APPLY_TEXT = "Tell us which role you are interested in, a little about yourself, and share a CV or links to what you have built or done, such as GitHub or a portfolio. There is no application form and no account to create.",
        WORK_APPLY_BUTTON = "Apply by email",
        OPEN_SOURCE_TITLE = "Open source, in the open.",
        OPEN_SOURCE_TEXT = ("Much of " . IdealisticOfficeVariable::APPLICATION_NAME . " is open source. Read how the apps work, check what they do, and build on them."),
        COMMANDS_TEXT = ("Install a portal in a channel or group, remove it again, and tag " . IdealisticOfficeVariable::APPLICATION_NAME . " whenever you want an answer. Messages without a tag are kept as history and never answered."),
        ENTERPRISE_TITLE = IdealisticOfficePlans::ENTERPRISE_NAME,
        ENTERPRISE_TEXT = "A plan shaped around your organization. Enterprise plans are arranged by email only.",
        TRUST = array(
        array("title" => "European company", "text" => IdealisticOfficeVariable::COMPANY_NAME . " is registered in " . IdealisticOfficeVariable::COMPANY_COUNTRY . ", in the European Union.", "label" => "Company register", "url" => IdealisticOfficeVariable::COMPANY_DETAILS),
        array("title" => "EU trademark", "text" => (IdealisticOfficeVariable::APPLICATION_NAME . " is European Union trademark no. ") . IdealisticOfficeVariable::TRADEMARK_NUMBER . ".", "label" => "View on EUIPO", "url" => IdealisticOfficeVariable::TRADEMARK_URL),
        array("title" => "Data deleted in " . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days", "text" => "Hosted entirely within the European Economic Area, never kept permanently, never analyzed and never sold.", "label" => "How we handle data", "url" => "#data"),
        array("title" => "Human support", "text" => "Real people answer at " . IdealisticOfficeVariable::SUPPORT_EMAIL . ". " . IdealisticOfficeVariable::SUPPORT_RESPONSE_NOTE, "label" => "Write to us", "url" => "mailto:" . IdealisticOfficeVariable::SUPPORT_EMAIL),
    ),
        WEB_PANEL_NOTE = ("The web app has two parts: a chat, for fast-paced use, and a page where you change everything " . IdealisticOfficeVariable::APPLICATION_NAME . " lets you change. Logged in, you will be able to join others right on the website."),
        RETENTION_TITLE = "Never kept forever.",
        RETENTION_TEXT = "Your data is kept for 30 to 90 days, and then it is deleted. That is the whole policy.",
        RETENTION_NOTE = "Data is kept for " . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " days at minimum, for legal reasons, and for up to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days. You can choose " . IdealisticOfficeVariable::DATA_RETENTION_OPTION_MINIMUM_DAYS . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days as an option, and nothing is kept permanently.",
        RETENTION_FACTS = array(
        array("value" => IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS, "unit" => "days minimum", "text" => "The shortest we keep data is " . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " days, which is required for legal reasons."),
        array("value" => IdealisticOfficeVariable::DATA_RETENTION_OPTION_MINIMUM_DAYS . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS, "unit" => "days, your option", "text" => "Prefer a longer period? Choose anything from " . IdealisticOfficeVariable::DATA_RETENTION_OPTION_MINIMUM_DAYS . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days."),
        array("value" => "Never", "unit" => "kept permanently", "text" => "When the period ends, the data is deleted. Nothing is retained forever."),
    ),
        RETENTION_PROMISES = array(
        array("title" => "No permanent retention", "text" => "There is no archive of your conversations. After the period you are on, they are gone."),
        array("title" => "No data analysis", "text" => "We do not analyze your data. It is only used to answer you when you ask."),
        array("title" => "No data selling", "text" => "We do not sell data, to anyone, ever. We are not in that business."),
    ),
        STEPS = array(
        array("number" => "01", "title" => "Humans talk.", "text" => "Conversations stay between people, exactly like before. Nobody is interrupted and nothing changes about how you talk."),
        array("number" => "02", "title" => "AI tracks.", "text" => ("Quietly, in the background, " . IdealisticOfficeVariable::APPLICATION_NAME . " keeps track of what was said, what was decided and what was promised.")),
        array("number" => "03", "title" => "AI speaks when asked.", "text" => "The AI never starts a conversation. Its input is only ever added through human interaction, when someone tags it."),
    ),
        FIXES = array(
        array("word" => "Disputes", "text" => "Who said what, and who agreed to what? The conversation remembers, so nobody has to scroll for hours or argue about it."),
        array("word" => "Disagreements", "text" => "When people see things differently, an impartial record of the discussion helps everyone find common ground faster."),
        array("word" => "Silent chats", "text" => "Chats go quiet when nothing moves forward. With something that keeps track, there is always a next thing to pick up."),
    ),
        PLATFORM_GROUPS = array(
        array(
            "id" => "install",
            "label" => "Deploy",
            "title" => "Deploy it where people already talk.",
            "text" => (IdealisticOfficeVariable::APPLICATION_NAME . " runs inside the places your people already talk. No new place to go."),
            "items" => array(
                array("key" => "discord", "name" => "Discord", "mark" => "D", "color" => "#5865F2", "action" => "Add to Discord", "url" => IdealisticOfficeVariable::DISCORD_APP_URL, "text" => "Bring it into your server and let everyone keep talking as usual."),
                array("key" => "telegram", "name" => "Telegram", "mark" => "T", "color" => "#26A5E4", "action" => "Add to Telegram", "url" => IdealisticOfficeVariable::TELEGRAM_APP_URL, "text" => "Add it to a group or topic and keep the whole thread on track."),
                array("key" => "whatsapp", "name" => "WhatsApp", "mark" => "W", "color" => "#25D366", "action" => "Add to WhatsApp", "url" => IdealisticOfficeVariable::WHATSAPP_APP_URL, "text" => "The group chat everybody lives in, finally with a memory."),
                array("key" => "widget", "name" => "Chat widget", "mark" => "</>", "color" => "#3B82F6", "action" => "Install the widget", "url" => "#setup", "text" => ("One line of code puts " . IdealisticOfficeVariable::APPLICATION_NAME . " on your own website.")),
            ),
        ),
        array(
            "id" => "configure",
            "label" => "Configure",
            "title" => "Configure it from anywhere.",
            "text" => ("Every app below can configure " . IdealisticOfficeVariable::APPLICATION_NAME . ", and so can a direct message on Discord, Telegram or WhatsApp. Conversations happen where it is deployed: Discord, Telegram, WhatsApp and the chat widget."),
            "items" => array(
                array("key" => "discord-dm", "name" => "Discord DMs", "mark" => "D", "color" => "#5865F2", "action" => "Open Discord", "url" => IdealisticOfficeVariable::DISCORD_APP_URL, "text" => ("Send " . IdealisticOfficeVariable::APPLICATION_NAME . " a direct message to configure it.")),
                array("key" => "telegram-dm", "name" => "Telegram DMs", "mark" => "T", "color" => "#26A5E4", "action" => "Open Telegram", "url" => IdealisticOfficeVariable::TELEGRAM_APP_URL, "text" => "Configure it in a private chat with the bot."),
                array("key" => "whatsapp-dm", "name" => "WhatsApp DMs", "mark" => "W", "color" => "#25D366", "action" => "Open WhatsApp", "url" => IdealisticOfficeVariable::WHATSAPP_APP_URL, "text" => "Configure it in a direct message on WhatsApp."),
                array("key" => "messenger", "name" => "Messenger", "mark" => "M", "color" => "#0A7CFF", "action" => "Configure on Messenger", "url" => IdealisticOfficeVariable::MESSENGER_APP_URL, "text" => "Set up and manage your portals right from Messenger."),
                array("key" => "instagram", "name" => "Instagram", "mark" => "I", "color" => "#E1306C", "action" => "Configure on Instagram", "url" => IdealisticOfficeVariable::INSTAGRAM_APP_URL, "text" => ("Manage " . IdealisticOfficeVariable::APPLICATION_NAME . " through Instagram messages.")),
                array("key" => "email", "name" => "Email", "mark" => "@", "color" => "#9AA4B5", "action" => IdealisticOfficeVariable::REPLY_EMAIL, "url" => "mailto:" . IdealisticOfficeVariable::REPLY_EMAIL, "text" => ("Prefer email? Configure it by writing to " . IdealisticOfficeVariable::APPLICATION_NAME . ".")),
                array("key" => "ios", "name" => "iOS", "mark" => "iOS", "color" => "#F5F7FB", "action" => "Get it for iOS", "url" => IdealisticOfficeVariable::IOS_APP_URL, "text" => "Configure from your iPhone or iPad."),
                array("key" => "android", "name" => "Android", "mark" => "A", "color" => "#3DDC84", "action" => "Get it for Android", "url" => IdealisticOfficeVariable::ANDROID_APP_URL, "text" => "Configure from your phone or tablet."),
                array("key" => "web", "name" => "Web app", "mark" => "W", "color" => "#60A5FA", "action" => "See your structure", "url" => IdealisticOfficeVariable::PORTAL_STRUCTURE_URL, "text" => "A page that shows everything you set up, from portals and contacts to roles, access and scheduled tasks, and a chat for fast-paced use. Logged in, you will be able to join others right on the website."),
            ),
        ),
    ),
        REPOSITORIES = array(
        array("name" => (IdealisticOfficeVariable::APPLICATION_NAME . " Discord App"), "text" => ("Discord implementation of the " . IdealisticOfficeVariable::APPLICATION_NAME . " application."), "language" => "PHP", "url" => IdealisticOfficeVariable::GITHUB_URL . "/Idealistic-Discord-App"),
        array("name" => (IdealisticOfficeVariable::APPLICATION_NAME . " Telegram App"), "text" => ("Telegram implementation of the " . IdealisticOfficeVariable::APPLICATION_NAME . " application."), "language" => "PHP", "url" => IdealisticOfficeVariable::GITHUB_URL . "/Idealistic-Telegram-App"),
        array("name" => (IdealisticOfficeVariable::APPLICATION_NAME . " Android App"), "text" => ("Hybrid (web to local) Android app for the " . IdealisticOfficeVariable::APPLICATION_NAME . " application."), "language" => "Java", "url" => IdealisticOfficeVariable::GITHUB_URL . "/idealistic-Android-App"),
        array("name" => (IdealisticOfficeVariable::APPLICATION_NAME . " iOS App"), "text" => ("Hybrid (web to local) iOS app for the " . IdealisticOfficeVariable::APPLICATION_NAME . " application."), "language" => "Swift", "url" => IdealisticOfficeVariable::GITHUB_URL . "/Idealistic-iOS-App"),
    ),
        SETUP_TABS = array(
        array("id" => "discord", "name" => "Discord", "steps" => array(
            "Press <strong>Add to Discord</strong> and pick the server you want to use it in.",
            ("Get the ID of your portal by configuring " . IdealisticOfficeVariable::APPLICATION_NAME . " in a direct message on Discord, or from any other app."),
            "In the channel or thread you want, run the setup command with your portal ID.",
        ), "commands" => array(
            array("command" => "/idealistic-setup portal:<id> [scope]", "text" => "Install a portal in a channel or thread. The scope is either that channel or thread, or its parent channel with all of its threads.", "requires" => "Manage Channels permission, in servers only"),
            array("command" => "/idealistic-remove [scope]", "text" => "Uninstall the portal of a channel or thread.", "requires" => "Manage Channels permission, in servers only"),
            array("command" => "@Idealistic your question", "text" => ("Ask " . IdealisticOfficeVariable::APPLICATION_NAME . " something."), "requires" => "Anyone in the channel"),
        ), "link" => array(IdealisticOfficeVariable::DISCORD_APP_URL, "Add to Discord")),
        array("id" => "telegram", "name" => "Telegram", "steps" => array(
            "Press <strong>Add to Telegram</strong> and add the bot to your group.",
            ("Get the ID of your portal by configuring " . IdealisticOfficeVariable::APPLICATION_NAME . " in a private chat with the bot, or from any other app."),
            "In the group or topic you want, send the setup command followed by your portal ID.",
        ), "commands" => array(
            array("command" => "/idealistic_setup <portal>", "text" => "Install a portal in a group or topic.", "requires" => "The Change Group Info admin right, in groups only"),
            array("command" => "/idealistic_remove", "text" => "Uninstall the portal of a group or topic.", "requires" => "The Change Group Info admin right, in groups only"),
            array("command" => "@Idealistic your question", "text" => ("Ask " . IdealisticOfficeVariable::APPLICATION_NAME . " something."), "requires" => "Anyone in the group"),
        ), "link" => array(IdealisticOfficeVariable::TELEGRAM_APP_URL, "Add to Telegram")),
        array("id" => "whatsapp", "name" => "WhatsApp", "steps" => array(
            ("Press <strong>Open WhatsApp</strong> and message " . IdealisticOfficeVariable::APPLICATION_NAME . " directly to configure it and get the ID of your portal, just like on Discord and Telegram."),
            "In the WhatsApp group you want, send the setup command followed by your portal ID.",
        ), "commands" => array(
            array("command" => "/idealistic-setup <portal>", "text" => "Install a portal in a group.", "requires" => "Groups only"),
            array("command" => "/idealistic-remove", "text" => "Uninstall the portal of a group.", "requires" => "Groups only"),
            array("command" => "@Idealistic your question", "text" => ("Mention " . IdealisticOfficeVariable::APPLICATION_NAME . " in a message to ask it something."), "requires" => "Anyone in the group"),
        ), "note" => "Full installation documentation for WhatsApp is not available yet.", "link" => array(IdealisticOfficeVariable::WHATSAPP_APP_URL, "Open WhatsApp")),
        array("id" => "widget", "name" => "Chat widget", "steps" => array(
            "Paste this line just before the closing <code>&lt;/body&gt;</code> tag of your website.",
            "Replace <code>" . IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT_ID_PLACEHOLDER . "</code> with the ID of your portal.",
            "A chat button appears on your website. That is all.",
        ), "code" => IdealisticOfficeVariable::PORTAL_EXTERNAL_SCRIPT, "link" => array(IdealisticOfficeVariable::PORTAL_CHAT_URL, "Try it first")),
        array("id" => "configure", "name" => "Configure from any app", "steps" => array(
            ("Send " . IdealisticOfficeVariable::APPLICATION_NAME . " a direct message on Discord, Telegram or WhatsApp, or open any of <a href=\"") . IdealisticOfficeVariable::MESSENGER_APP_URL . "\">Messenger</a>, <a href=\"" . IdealisticOfficeVariable::INSTAGRAM_APP_URL . "\">Instagram</a>, <a href=\"mailto:" . IdealisticOfficeVariable::REPLY_EMAIL . "\">email</a>, <a href=\"" . IdealisticOfficeVariable::IOS_APP_URL . "\">iOS</a>, <a href=\"" . IdealisticOfficeVariable::ANDROID_APP_URL . "\">Android</a> or the <a href=\"" . IdealisticOfficeVariable::PORTAL_CHAT_URL . "\">chat</a>.",
            ("Talk to " . IdealisticOfficeVariable::APPLICATION_NAME . " there to create and manage your portals."),
            "Then deploy it on Discord, Telegram, WhatsApp or the chat widget. On the web, once you are logged in, you will be able to join others right on the website.",
        ), "link" => array(IdealisticOfficeVariable::PORTAL_STRUCTURE_URL, "See your structure")),
    ),
        FAQ = array(
        array("q" => ("What is " . IdealisticOfficeVariable::APPLICATION_NAME . "?"), "a" => "A way for humans to communicate harmoniously with AI. You talk with other people as usual, the AI keeps track of the conversation, and it only joins in when a human asks it to."),
        array("q" => ("Which AI does " . IdealisticOfficeVariable::APPLICATION_NAME . " use?"), "a" => self::AI_MODELS_NOTE . " Messages are sent to OpenAI only so a reply can be produced when a human asks, as described in the <a href=\"" . IdealisticOfficeVariable::PRIVACY_POLICY_URL . "\">Privacy Policy</a>."),
        array("q" => "Does the AI ever talk on its own?", "a" => "No. The AI never starts a conversation and never interrupts. On Discord and Telegram it only answers messages that tag it, and everything else is kept as history. Its input is only added through human interaction."),
        array("q" => "Where can I use it?", "a" => (IdealisticOfficeVariable::APPLICATION_NAME . " is deployed on Discord, Telegram, WhatsApp and as a chat widget on your own website. You can configure it from all of those, including in direct messages on Discord, Telegram and WhatsApp, and also from Messenger, Instagram, email and the iOS, Android and web apps. On the web, once you are logged in, you will be able to join others right on idealistic.ai.")),
        array("q" => "How long is my data kept?", "a" => "For " . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days, and then it is deleted. " . IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS . " days is the minimum, kept for legal reasons. You can choose any period from " . IdealisticOfficeVariable::DATA_RETENTION_OPTION_MINIMUM_DAYS . " to " . IdealisticOfficeVariable::DATA_RETENTION_MAXIMUM_DAYS . " days as an option. Nothing is retained permanently, we do not analyze your data, and we do not sell it. We are not in that business."),
        array("q" => "How can it settle a dispute?", "a" => "Because it keeps track of what was said and decided, anyone can ask what was actually agreed instead of relying on memory. The record is the same for everybody."),
        array("q" => "How much does it cost?", "a" => ("Three monthly plans, shown in the pricing section above: " . IdealisticOfficePlans::NAMES[0] . " with a free trial, " . IdealisticOfficePlans::NAMES[1] . " and " . IdealisticOfficePlans::NAMES[2] . ". ") . IdealisticOfficePlans::NAMES[1] . ", " . IdealisticOfficePlans::NAMES[2] . " and " . IdealisticOfficePlans::ENTERPRISE_NAME . " also include " . IdealisticOfficePlans::CLUB_NAME . ". " . IdealisticOfficePlans::NAMES[2] . " and " . IdealisticOfficePlans::ENTERPRISE_NAME . (" can also remove the " . IdealisticOfficeVariable::APPLICATION_NAME . " logo on every platform. ") . IdealisticOfficePlans::ENTERPRISE_NAME . " adds a statistics panel and is arranged by email only, with <a href=\"mailto:" . IdealisticOfficeVariable::BUSINESS_EMAIL . "\">" . IdealisticOfficeVariable::BUSINESS_EMAIL . "</a>.", "pricing" => true),
        array("q" => ("Can I remove the " . IdealisticOfficeVariable::APPLICATION_NAME . " logo?"), "a" => ("Answers carry a small " . IdealisticOfficeVariable::APPLICATION_NAME . " logo: on the chat widget, as an embedded message on Discord and as a Powered by line at the end of Telegram and WhatsApp messages. It can be removed on ") . IdealisticOfficePlans::NAMES[2] . " and " . IdealisticOfficePlans::ENTERPRISE_NAME . ".", "pricing" => true),
        array("q" => "Is a free trial available?", "a" => ("Yes. " . IdealisticOfficePlans::NAMES[0] . " includes a fully functional 7-day free trial, allowing you to explore its core features before subscribing. The other plans start right away."), "pricing" => true),
        array("q" => "What are credits?", "a" => ("Credits are used each time " . IdealisticOfficeVariable::APPLICATION_NAME . " answers: ") . IdealisticOfficeLimit::CHAT_PORTAL_MESSAGE_USAGE . " credit for a portal or external message and " . IdealisticOfficeLimit::DEFAULT_MESSAGE_USAGE . " credits for an app or internal message. Every plan includes a monthly amount, and larger plans include more.", "pricing" => true),
        array("q" => "What happens if I exceed my credit limit?", "a" => "To prevent unexpected charges, your portal will temporarily pause processing new messages once your monthly credit limit is reached. We will notify you before this happens, and you can easily upgrade your plan at any time to resume service instantly.", "pricing" => true),
        array("q" => "Can I switch plans?", "a" => "Yes, you can upgrade or downgrade your subscription at any time. If you upgrade, your new credit limits take effect immediately and the cost is prorated. If you downgrade, the new rate and limits will apply at the start of your next monthly billing cycle.", "pricing" => true),
        array("q" => "Does language coverage affect pricing?", "a" => "No, language coverage does not affect pricing. " . IdealisticOfficeVariable::APPLICATION_NAME . " is built for global communication. You are charged the exact same standard credit rate (" . IdealisticOfficeLimit::CHAT_PORTAL_MESSAGE_USAGE . " credit for portal messages, " . IdealisticOfficeLimit::DEFAULT_MESSAGE_USAGE . " credits for app messages) regardless of the language used by you or your visitors.", "pricing" => true),
        array("q" => "How is data handled?", "a" => "Security and privacy are our top priorities. All raw conversational data and uploaded files are hosted on secure, dedicated servers within the European Economic Area (EEA). While we use OpenAI's enterprise API for processing, your data is explicitly opted out of model training, so your inputs are never used to train foundational models. Furthermore, we never sell your data, and we strictly compartmentalize access controls. Read the <a href=\"" . IdealisticOfficeVariable::PRIVACY_POLICY_URL . "\">Privacy Policy</a> and the <a href=\"" . IdealisticOfficeVariable::TERMS_OF_USE_URL . "\">Terms of Use</a>. " . self::RETENTION_NOTE . " You can also ask for your data to be deleted, see the <a href=\"" . IdealisticOfficeVariable::USER_DATA_DELETION_INSTRUCTIONS_URL . "\">data deletion instructions</a>."),
        array("q" => "Is " . IdealisticOfficeVariable::APPLICATION_NAME . " GDPR compliant?", "a" => "Yes, we strictly adhere to GDPR regulations. We offer full transparency into our data lifecycle, giving you the right to access, rectify, or request the anonymization of your Personally Identifiable Information (PII) from our active systems. Any necessary international data transfers are safeguarded by legally approved mechanisms, including the EU-US Data Privacy Framework and Standard Contractual Clauses."),
        array("q" => "Do I need technical skills to integrate this?", "a" => "Not at all. Copy and paste a single line of code into your website's HTML, and everything will work immediately."),
        array("q" => "Will installing the widget slow down my website?", "a" => "Not at all. The " . IdealisticOfficeVariable::APPLICATION_NAME . " widget is highly optimized and lightweight. It loads asynchronously, meaning it won't negatively impact your website's initial page load speed or performance metrics."),
        array("q" => "Can multiple team members manage the same portal?", "a" => "Yes! You can collaborate with others seamlessly. Our system allows you to tag specific team members, control who works and when on your shared portals, and schedule automated prompts to manage routines effortlessly."),
        array("q" => "What can I do on the web?", "a" => self::WEB_PANEL_NOTE),
        array("q" => ("Is " . IdealisticOfficeVariable::APPLICATION_NAME . " open source?"), "a" => "Much of it is. The Discord, Telegram, Android and iOS apps are public on <a href=\"" . IdealisticOfficeVariable::GITHUB_URL . "\">GitHub</a>."),
        array("q" => ("Who builds " . IdealisticOfficeVariable::APPLICATION_NAME . "?"), "a" => (IdealisticOfficeVariable::APPLICATION_NAME . " is built by humans at ") . IdealisticOfficeVariable::COMPANY_NAME . (". The AI inside " . IdealisticOfficeVariable::APPLICATION_NAME . " only answers when a human asks.")),
        array("q" => "How do I get support?", "a" => "Write to <a href=\"mailto:" . IdealisticOfficeVariable::SUPPORT_EMAIL . "\">" . IdealisticOfficeVariable::SUPPORT_EMAIL . "</a>. " . IdealisticOfficeVariable::SUPPORT_RESPONSE_NOTE),
    );

    public static function isNativeApp(?string $userAgent): bool
    {
        return !empty($userAgent)
            && preg_match('/IdealisticApp|; wv\)|(iPhone|iPad|iPod)(?!.*Safari)/i', $userAgent) === 1;
    }

    public static function getFaq(bool $withPricing): array
    {
        return $withPricing
            ? self::FAQ
            : array_values(array_filter(self::FAQ, fn(array $item) => empty($item['pricing'])));
    }
}

class IdealisticOfficePlans implements PhpAsyncSerializable
{
    public const
        CURRENCY = "EUR",
        TYPE = "Monthly Subscription",
        VAT = "Excluded for registered businesses, Included for individuals",
        FREE_TRIAL = "7 days free trial",
        DISCLAIMER = ("Subscription details may not be fully available on the " . IdealisticOfficeVariable::APPLICATION_NAME . " Android/iOS/iPadOS app"),
        NAMES = array("idealistic.pro", "idealistic.vip", "idealistic.red"),
        ENTERPRISE_NAME = "idealistic.enterprises",
        CLUB_NAME = "idealistic.club",
        STATISTICS_PANEL_NAME = "Statistics panel",
        CLUB_FROM_PLAN = 1,
        LOGO_REMOVAL_MINIMUM_CREDITS = 100_000,
        LOGO_TEXT = ("Small " . IdealisticOfficeVariable::APPLICATION_NAME . " logo on every platform"),
        LOGO_REMOVAL_TEXT = ("Remove the " . IdealisticOfficeVariable::APPLICATION_NAME . " logo on every platform"),
        POWERED_BY_TEXT = ("Powered by " . IdealisticOfficeVariable::APPLICATION_NAME),
        PLANS = array(
        array("price" => 9.9, "credits" => 15_000, "context" => 6_000, "has_logo" => true, "trial" => true, "paddle_price_id" => "pri_01kmxss0cjz886wk13kkf2k3bf"),
        array("price" => 19.9, "credits" => 30_000, "context" => 16_000, "has_logo" => true, "trial" => false, "paddle_price_id" => "pri_01kmxswej379jhg41jnpztdk8c"),
        array("price" => 59.9, "credits" => 100_000, "context" => 40_000, "has_logo" => false, "trial" => false, "paddle_price_id" => "pri_01kn7j7fh7d25ned7f1k5wh8c1"),
    ),
        MINIMUM_PRICE = self::PLANS[0]["price"],
        MINIMUM_CREDITS = self::PLANS[0]["credits"],
        MINIMUM_SAVING = 1.0,
        SAVING_TEXT = "Saves %s a month compared with the %s rate",
        RATE_TEXT = "%s per 1,000 credits",
        CHECKOUT_TEXT = "Start free trial",
        SUBSCRIBE_TEXT = "Subscribe",
        CHECKOUT_NOTE = "After checkout, a sign-in link is sent to your email.",
        ENTERPRISE_HISTORY_BUDGET_CHARACTERS = 100_000,
        HISTORY_TEXT = "Chat history",
        MAXIMUM_EXPECTED_PRICE_PER_PORTAL_MESSAGE = self::MINIMUM_PRICE / self::MINIMUM_CREDITS,
        MAXIMUM_EXPECTED_PRICE_PER_APP_MESSAGE = self::MINIMUM_PRICE / self::MINIMUM_CREDITS * IdealisticOfficeLimit::DEFAULT_MESSAGE_USAGE;

    public static function getMaximumPlanCredits(): int
    {
        return max(array_column(self::PLANS, "credits"));
    }

    public static function getHistoryBudgetCharacters(int $includedCredits): int
    {
        $budget = IdealisticOfficeLimit::CHAT_PORTAL_HISTORY_BUDGET_CHARACTERS;

        foreach (self::PLANS as $plan) {
            if ($includedCredits >= $plan["credits"]) {
                $budget = $plan["context"];
            }
        }
        return $includedCredits > self::getMaximumPlanCredits()
            ? self::ENTERPRISE_HISTORY_BUDGET_CHARACTERS
            : $budget;
    }

    public static function getPricePerThousandCredits(int $position): float
    {
        $plan = self::PLANS[$position];
        return $plan["price"] / $plan["credits"] * 1_000;
    }

    public static function getSlug(string $name): string
    {
        $position = strrpos($name, ".");
        return strtolower($position === false ? $name : substr($name, $position + 1));
    }

    public static function getSaving(int $position): ?array
    {
        $plan = self::PLANS[$position] ?? null;

        if ($plan === null || $position === 0) {
            return null;
        }
        $equivalent = round($plan["credits"] * (self::MINIMUM_PRICE / self::MINIMUM_CREDITS), 2);
        $saved = round($equivalent - $plan["price"], 2);

        return $saved >= self::MINIMUM_SAVING
            ? array("amount" => $saved, "percent" => (int)round($saved / $equivalent * 100), "equivalent" => $equivalent)
            : null;
    }
}

class IdealisticOfficeSettings implements PhpAsyncSerializable
{
    public const
        SEND_VOICE_REPLY = "send_voice_reply",
        SELECTED_TIMEZONE = "selected_timezone",
        SELECTED_LANGUAGE = "selected_language",
        NEW_FULL_NAME = "new_full_name",
        DATA_RETENTION_DAYS = "data_retention_days";

    public const
        DEFAULTS = array(
        self::DATA_RETENTION_DAYS => IdealisticOfficeVariable::DATA_RETENTION_MINIMUM_DAYS,
        self::SEND_VOICE_REPLY => false,
        self::SELECTED_TIMEZONE => IdealisticOfficeVariable::DEFAULT_TIME_ZONE,
        self::SELECTED_LANGUAGE => IdealisticOfficeVariable::DEFAULT_LANGUAGE
    ),
        DISABLED = array();
}

class IdealisticOfficePromptFields implements PhpAsyncSerializable
{

    public static function nullableDecimalArray(string|array $s, ?array $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::DECIMAL_ARRAY,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableDecimal(string|array $s, mixed $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::DECIMAL,
            null,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableBooleanArray(string|array $s, ?bool $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::BOOLEAN_ARRAY,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableBoolean(string|array $s, ?bool $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::BOOLEAN,
            null,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableIntegerArray(string|array $s, ?array $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::INTEGER_ARRAY,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableInteger(string|array $s, mixed $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::INTEGER,
            null,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableStringArray(string|array $s, ?array $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::STRING_ARRAY,
            IdealisticOfficeLimit::MAX_PROCESSED_TASKS,
            true,
            $s,
            $enums,
            $default
        );
    }

    public static function nullableString(string|array $s, ?string $default = null, ?array $enums = null): AIFieldObject
    {
        return new AIFieldObject(
            AIField::STRING,
            IdealisticOfficeLimit::K16_LENGTH,
            true,
            $s,
            $enums,
            $default
        );
    }

}

class IdealisticOfficeAccessPlatform implements PhpAsyncSerializable
{

    public const
        DISCORD = 10,
        TELEGRAM = 11,
        MESSENGER = 13,
        WHATSAPP = 14,
        VIBER = -1,
        INSTAGRAM = 12,
        EMAIL = 0,
        NATIVE = 15;
    private const ALL_PLATFORMS = array(
        self::DISCORD => "Discord",
        self::TELEGRAM => "Telegram",
        self::MESSENGER => "Messenger",
        self::WHATSAPP => "WhatsApp",
        self::VIBER => "Viber",
        self::INSTAGRAM => "Instagram",
        self::EMAIL => "Email",
        self::NATIVE => IdealisticOfficeVariable::APPLICATION_NAME . ": Web/Android/iOS Application"
    );

    public static function getValidPlatforms(): array
    {
        $platforms = array();

        foreach (self::ALL_PLATFORMS as $id => $name) {
            if ($id !== -1) {
                $platforms[$id] = $name;
            }
        }
        return $platforms;
    }

    public static function getValidPlatformId(string $platform): ?int
    {
        $platform = strtolower($platform);

        foreach (self::ALL_PLATFORMS as $id => $name) {
            if (strtolower($name) === $platform) {
                return $id === -1
                    ? null
                    : $id;
            }
        }
        return null;
    }

}

class IdealisticOfficeLimit implements PhpAsyncSerializable
{

    public const
        MAX_PROCESSED_ROWS = 50_000,
        MAX_PROCESSED_TASKS = 50,

        HISTORY_PROMPT_SECONDS_TIMEOUT = 60,
        DATA_RETENTION_BATCH_ROWS = 100,

        MAX_ATTACHMENT_BYTES_SIZE = 25 * 1_024 * 1_024,
        MAX_ATTACHMENT_BYTES_PER_DAY = 1_024 * 1_024 * 1_024,

        MAX_ACCEPTED_IP_ADDRESSES = 4,
        MAX_ACCEPTED_UNIQUE_SESSIONS = 100,
        DEFAULT_URL_EXPIRATION_TIME = "30 minutes",
        EXTENDED_URL_EXPIRATION_TIME = "20 hours",
        LINK_REFRESH_COOLDOWN = "1 day",
        LINK_DOWNLOAD_SECONDS_TIMEOUT = 3,
        RANDOM_STRING_LENGTH = 10,
        URL_RANDOM_ID_LENGTH = 16,
        SESSION_ID_LENGTH = AccountSession::session_token_length,
        K3_LENGTH = 3_072,
        K8_LENGTH = 8_000,
        K11_LENGTH = 11_000,
        K10_LENGTH = 10_000,
        K2_LENGTH = 2_048,
        K4_LENGTH = 4_096,
        K1_LENGTH = 1_024,
        K16_LENGTH = 16_000,
        K13_LENGTH = 13_000,
        K12_LENGTH = 12_000,
        K15_LENGTH = 15_000,
        H128_LENGTH = 128,
        H512_LENGTH = 512,
        T64_LENGTH = 64,
        EMAIL_LENGTH = 384,
        MAX_EXTENSIONS_PER_OBJECT = 20,
        MAX_EXTENSIONS_PER_OBJECT_LARGE = 100,
        MAX_MODIFICATION_HISTORY_ENTRIES = 5,
        MAX_SEARCH_QUERY_LENGTH = 256,
        MAX_PAYPAL_EMAILS = 5,
        WEB_SEARCH_MAX_RESULTS = 4,
        EXTERNAL_APPLICATION_QUERY_SECONDS = 0.05,
        APPLICATION_DATA_ACTIVE_QUERY_MILLISECONDS = 500,
        APPLICATION_DATA_INACTIVE_QUERY_MILLISECONDS = 5_000,

        CHAT_PORTAL_MAX_CONSIDERATION_TIME = "1 day",
        CHAT_PORTAL_MAX_HISTORY_TIME = "3 days",
        CHAT_PORTAL_MAX_PROCESSING_TIME = "1 minute",
        CHAT_PORTAL_MESSAGE_COOLDOWN = "2 seconds",
        CHAT_PORTAL_MESSAGE_USAGE = 1,
        CHAT_PORTAL_MAX_REPLY_LENGTH = self::K4_LENGTH,
        CHAT_PORTAL_MAX_DAILY_FAILED_ACCESSES = 10,
        CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_SESSION = 100,
        CHAT_PORTAL_MAX_HOURLY_MESSAGES_PER_IP = 1_000,
        CHAT_PORTAL_DEFAULT_MESSAGE_LENGTH = 256,
        CHAT_PORTAL_HISTORY_MESSAGE_CHARACTERS = 1_500,
        CHAT_PORTAL_HISTORY_REPLY_CHARACTERS = 500,
        CHAT_PORTAL_HISTORY_BUDGET_CHARACTERS = 6_000,
        CHAT_PORTAL_HISTORY_NAME_LENGTH = 40,
        CHAT_PORTAL_SKIP_BOT_VERIFICATION = true,

        DEFAULT_MESSAGE_USAGE = 10,

        SHORTCUT_WORD_MIN_LENGTH = 3,
        SHORTCUT_WORD_MAX_LENGTH = 16,
        SHORTCUT_REPLACEMENT_MAX_LENGTH = 64,

        MAX_FOLDERS = 500,
        FOLDER_MAX_COMMENTS = 1_000,
        FOLDER_ARCHIVE_AFTER_DAYS = 14,

        ATTACHMENT_LIMIT = array(
        IdealisticOfficeAccessPlatform::DISCORD => 9, // 10 actually, 1 reserved for audio
        IdealisticOfficeAccessPlatform::TELEGRAM => 10, // Audio can be sent separately
        IdealisticOfficeAccessPlatform::MESSENGER => 1,
        IdealisticOfficeAccessPlatform::INSTAGRAM => 1,
        IdealisticOfficeAccessPlatform::WHATSAPP => 1,
        IdealisticOfficeAccessPlatform::NATIVE => 1
    ),
        ATTACHMENT_BYTES_LIMIT = array(
        IdealisticOfficeAccessPlatform::DISCORD => 8 * 1_024 * 1_024,
        IdealisticOfficeAccessPlatform::TELEGRAM => 20 * 1_024 * 1_024,
        IdealisticOfficeAccessPlatform::MESSENGER => 25 * 1_024 * 1_024,
        IdealisticOfficeAccessPlatform::INSTAGRAM => 8 * 1_024 * 1_024,
        IdealisticOfficeAccessPlatform::WHATSAPP => array(
            IdealisticOfficeFileType::IMAGE => 5 * 1_024 * 1_024,
            IdealisticOfficeFileType::AUDIO => 16 * 1_024 * 1_024,
            IdealisticOfficeFileType::VIDEO => 16 * 1_024 * 1_024,
            IdealisticOfficeFileType::DOCUMENT => 100 * 1_024 * 1_024
        ),
        IdealisticOfficeAccessPlatform::NATIVE => self::MAX_ATTACHMENT_BYTES_SIZE
    ),
        MESSAGE_CHARACTER_LIMIT = array(
        IdealisticOfficeAccessPlatform::DISCORD => 2_000,
        IdealisticOfficeAccessPlatform::TELEGRAM => 4_096,
        IdealisticOfficeAccessPlatform::MESSENGER => 2_000,
        IdealisticOfficeAccessPlatform::INSTAGRAM => 1_000,
        IdealisticOfficeAccessPlatform::WHATSAPP => (false ? 65_536 : 4_096), // 65_536 for Meta
        IdealisticOfficeAccessPlatform::NATIVE => IdealisticOfficeLimit::CHAT_PORTAL_MAX_REPLY_LENGTH,
    ),
        MONTHLY_LATE_NOTIFICATION_LIMIT = array(
        IdealisticOfficeAccessPlatform::WHATSAPP => 1_000
    ),
        LATE_NOTIFICATION_TIME = array(
        IdealisticOfficeAccessPlatform::WHATSAPP => 86_400 // 24 hours
    ),
        SUPPORTS_VOICE_MESSAGES = array(
        IdealisticOfficeAccessPlatform::MESSENGER,
        IdealisticOfficeAccessPlatform::WHATSAPP,
        IdealisticOfficeAccessPlatform::DISCORD,
        IdealisticOfficeAccessPlatform::TELEGRAM
    ),
        DISALLOWS_MESSAGE_EDITS = array(
        IdealisticOfficeAccessPlatform::MESSENGER,
        IdealisticOfficeAccessPlatform::INSTAGRAM,
        IdealisticOfficeAccessPlatform::WHATSAPP,
        IdealisticOfficeAccessPlatform::EMAIL
    ),
        ATTACHMENT_URL_ONLY = array(
        IdealisticOfficeAccessPlatform::MESSENGER,
        IdealisticOfficeAccessPlatform::INSTAGRAM,
        IdealisticOfficeAccessPlatform::WHATSAPP,
        IdealisticOfficeAccessPlatform::NATIVE
    ),
        SUPPORTS_CAPTIONED_ATTACHMENTS = array(
        IdealisticOfficeAccessPlatform::WHATSAPP,
        IdealisticOfficeAccessPlatform::DISCORD,
        IdealisticOfficeAccessPlatform::TELEGRAM,
        IdealisticOfficeAccessPlatform::EMAIL
    ),
        IS_USING_TYPING_ANIMATION = array(
        IdealisticOfficeAccessPlatform::MESSENGER,
        IdealisticOfficeAccessPlatform::INSTAGRAM,
        IdealisticOfficeAccessPlatform::NATIVE
    ),
        REASONING_EFFORT_PER_TASK_AI = array(
        IdealisticOfficeVariable::TASK_AI_CHEAP_MODEL => IdealisticOfficeVariable::TASK_AI_DECREASED_REASONING_EFFORT,
        IdealisticOfficeVariable::TASK_AI_BALANCED_MODEL => IdealisticOfficeVariable::TASK_AI_NO_REASONING_EFFORT,
        IdealisticOfficeVariable::TASK_AI_EXPENSIVE_MODEL => IdealisticOfficeVariable::TASK_AI_NO_REASONING_EFFORT
    ),
        ALL_LANGUAGES = array(
        "english",
        "spanish",
        "french",
        "german",
        "italian",
        "portuguese",
        "dutch",
        "russian",
        "chinese",
        "japanese",
        "korean",
        "hindi",
        "arabic",
        "bengali",
        "turkish",
        "vietnamese",
        "polish",
        "swedish",
        "norwegian",
        "danish",
        "finnish",
        "greek",
        "hellenic",
        "czech",
        "hungarian",
        "romanian",
        "thai",
        "indonesian",
        "hebrew",
        "ukrainian",
        "malay",
        "filipino",
        "slovak",
        "croatian",
        "serbian",
        "bulgarian",
        "lithuanian",
        "latvian",
        "estonian",
        "slovenian",
        "icelandic"
    ),
        SEARCH_ENGINE_PER_LANGUAGE = array(
        "english" => "f06510cd3dcae4d65",
        "spanish" => "3064b6e0cd5e142c7",
        "french" => "63eb5e1f582964c52",
        "german" => "15790da5344a14ca5",
        "italian" => "43ee844bc65b948da",
        "portuguese" => "20a50ca7755b04644",
        "dutch" => "6791971c9815f40d9",
        "russian" => "b6c0d754ded774ba6",
        "chinese" => "97586fa81431a43fc",
        "japanese" => "80d1c3aa12b204949",
        "korean" => "5189add5eb655457e",
        "hindi" => "34ac48a7d074f4bc7",
        "arabic" => "8482726242d444eed",
        "bengali" => "41dc87abbb9374e55",
        "turkish" => "d6dab7363e60f4b23",
        "vietnamese" => "24a55476c631f40f3",
        "polish" => "f117eb59329714c86",
        "swedish" => "4274cbab295454ca9",
        "norwegian" => "32b25bbc82ac74ae4",
        "danish" => "f6e143f1c23a64798",
        "finnish" => "3251b328b879b4a79",
        "greek" => "204ccf8bebd464d0f",
        "hellenic" => "204ccf8bebd464d0f",
        "czech" => "5105525d3260249eb",
        "hungarian" => "a00cadb4d7e844361",
        "romanian" => "9299b4bf12aed4de8",
        "thai" => "0094c2359f7bb4f28",
        "indonesian" => "45de9686ac3e64ce8",
        "hebrew" => "2242253b032374853",
        "ukrainian" => "a7cd2a41bd5df4418",
        "malay" => "770f0e59f96bc4e25",
        "filipino" => "b4f889f5d976649cb",
        "slovak" => "b2b836caf820f4dff",
        "croatian" => "902cf556547114680",
        "serbian" => "151f1d69e63e248cd",
        "bulgarian" => "552e85da5e209417a",
        "lithuanian" => "a5cf1451beac54b93",
        "latvian" => "b02032d9d790a4b4f",
        "estonian" => "90fb12cfc0cf34a39",
        "slovenian" => "7651f105bc0d140e2",
        "icelandic" => "c207484d5166c4c2b"
    );

}

class IdealisticOfficeFileType implements PhpAsyncSerializable
{

    public const
        IMAGE = "image",
        AUDIO = "audio",
        VIDEO = "video",
        DOCUMENT = "document";

    public static function getTypeFromAttachment(AbstractIdealisticOfficeAttachment $attachment): string
    {
        if ($attachment->isImage()) {
            return self::IMAGE;
        } else if ($attachment->isAudio()) {
            return self::AUDIO;
        } else if ($attachment->isVideo()) {
            return self::VIDEO;
        } else {
            return self::DOCUMENT;
        }
    }

}

class IdealisticOfficePromptTask implements PhpAsyncSerializable
{

    public const
        TEAM_INFO = "TeamInfo",
        MANAGE_ROLES = "ManageRoles",
        MANAGE_INCLUDED_CREDIT_MEMBERS = "ManageIncludedCreditMembers",
        DATA_RETENTION = "DataRetention",
        ACCESS_CHECK = "AccessCheck",
        INFORMATION_ANALYSIS = "InformationAnalysis",
        INFORMATION_ANALYSIS_POSITIONS = "InformationAnalysisPositions",
        LINK_ANALYSIS = "LinkAnalysis",
        LINK_POSITIONS = "LinkPositions",
        ATTACHMENT_ANALYSIS = "AttachmentAnalysis",
        ATTACHMENT_POSITIONS = "AttachmentPositions",
        ATTACHMENT_NAMES = "AttachmentNames",
        CREATE_IMAGE = "CreateImage",
        GENERAL_VOICE_REPLY = "GeneralVoiceReply",
        CREATE_FILE = "CreateFile",
        MODIFY_FILE = "ModifyFile",
        USER_LANGUAGE_SELECTION = "UserLanguageSelection",
        USER_TIME_ZONE = "UserTimeZone",
        MANAGE_TIME_ACCESSES = "ManageTimeAccesses",
        MANAGE_WEEK_ACCESSES = "ManageWeeklyAccesses",
        MANAGE_FOLDERS = "ManageFolders",
        SHARE_INFORMATION = "ShareInformation",
        VOICE_REPLY_SETTING = "VoiceReplySetting",
        MANAGE_INSTRUCTIONS = "ManageInstructions",
        MANAGE_SHORTCUTS = "ManageShortcuts",
        MANAGE_PORTALS = "ManagePortals",
        MANAGE_PAYPAL_EMAILS = "ManagePaypalEmails",
        WEB_DATA = "WebData",
        MANAGE_SCHEDULER = "ManageScheduler",
        NEW_USERS = "NewUsers",

        TASKS_LIST = array(
        self::ACCESS_CHECK,
        self::INFORMATION_ANALYSIS,
        self::INFORMATION_ANALYSIS_POSITIONS,
        self::ATTACHMENT_ANALYSIS,
        self::ATTACHMENT_POSITIONS,
        self::ATTACHMENT_NAMES,
        self::TEAM_INFO,
        self::GENERAL_VOICE_REPLY,
        self::LINK_ANALYSIS,
        self::LINK_POSITIONS,
        self::USER_LANGUAGE_SELECTION,
        self::WEB_DATA,
        self::MANAGE_SHORTCUTS,
        self::MANAGE_PORTALS,
        self::VOICE_REPLY_SETTING,
        self::MANAGE_INSTRUCTIONS,
        self::MANAGE_ROLES,
        self::MANAGE_INCLUDED_CREDIT_MEMBERS,
        self::DATA_RETENTION,
        self::MANAGE_FOLDERS,
        self::SHARE_INFORMATION,
        self::USER_TIME_ZONE,
        self::MANAGE_TIME_ACCESSES,
        self::MANAGE_WEEK_ACCESSES,
        self::MANAGE_PAYPAL_EMAILS,
        self::MANAGE_SCHEDULER,
        self::CREATE_FILE,
        self::MODIFY_FILE,
        self::CREATE_IMAGE,
        self::NEW_USERS
    ),
        DEFAULT_ENABLED = array(
        self::ACCESS_CHECK,
        self::INFORMATION_ANALYSIS,
        self::INFORMATION_ANALYSIS_POSITIONS,
        self::ATTACHMENT_ANALYSIS,
        self::ATTACHMENT_POSITIONS,
        self::ATTACHMENT_NAMES,
        self::LINK_ANALYSIS,
        self::LINK_POSITIONS,
        self::USER_LANGUAGE_SELECTION,
        self::WEB_DATA,
        self::TEAM_INFO,
        self::GENERAL_VOICE_REPLY,
        self::VOICE_REPLY_SETTING,
        self::MANAGE_PAYPAL_EMAILS,
        self::USER_TIME_ZONE,
        self::MANAGE_TIME_ACCESSES,
        self::MANAGE_WEEK_ACCESSES,
        self::NEW_USERS,
        self::MANAGE_INCLUDED_CREDIT_MEMBERS,
        self::DATA_RETENTION
    ),
        SYSTEM_DISABLED = array(
        self::CREATE_IMAGE,
        self::CREATE_FILE,
        self::MODIFY_FILE,
        self::MANAGE_ROLES,
        self::MANAGE_SHORTCUTS,
        self::VOICE_REPLY_SETTING,
        self::GENERAL_VOICE_REPLY,
        self::MANAGE_SHORTCUTS
    );

    public static function isEnabled(AbstractIdealisticOfficeTeam $team, string $task): bool
    {
        $initial = $task;
        $task = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $task));
        $query = get_sql_query(
            IdealisticOfficeTable::ENABLED_TASKS_TABLE,
            null,
            array(
                null,
                array("team_id", "IS", null, 0),
                array("team_id", $team->getId()),
                null
            ),
            array(
                "DESC",
                "id"
            ),
            1
        );

        if (empty($query)) {
            return !in_array($initial, self::SYSTEM_DISABLED);
        } else if (isset($query[0]->{$task})) {
            return !in_array($initial, self::SYSTEM_DISABLED)
                && $query[0]->{$task} !== null;
        } else {
            if (!in_array($initial, self::DEFAULT_ENABLED)) {
                IdealisticOfficeError::simpleStore(
                    "The task/column '" . $task . "' does not exist in the '"
                    . IdealisticOfficeTable::ENABLED_TASKS_TABLE . "' table."
                );
            }
            return !in_array($initial, self::SYSTEM_DISABLED);
        }
    }

}

class IdealisticOfficeGeneralMessage implements PhpAsyncSerializable
{
    public const
        PROMPT_WAIT_RESPONSE = "Thinking...",
        PROMPT_WAIT_STILL_RESPONSE = "I am still thinking, please wait...",
        PROMPT_FAILED_PROCESSING = "The prompt failed to be processed. Please try again.",
        OUTCOME_FAILED_PROCESSING = "The outcome failed to be processed. Please try again.",
        EXCEPTION_THROWN = "An error was thrown while processing your request, our team has been notified and will get right on it!",
        ATTACHMENT_FAILED_PROCESSING = "One of the attachments you uploaded could not be processed.",
        TIME_TERMINATED_TASKS = "Some tasks failed to process on time and were terminated!";
}

class IdealisticOfficeQuery implements PhpAsyncSerializable
{
    public const
        USER_IDENTITY = array(
        "id",
        "creation_date",
        "user_name",
        "display_name",
        "platform_id",
        "platform_user_id",
        "platform_chat_id",
        "platform_chat_name",
        "platform_message_id",
        "creation_time_zone",
        "last_used_date",
        "last_used_time_zone",
    ),
        FOLDER_ATTACHED_PORTAL = array(
        "creation_reason",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "portal_id",
        "id"
    ),
        FOLDER_ATTACHED_SPECIFIC = array(
        "creation_reason",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "id"
    ),
        FOLDER = array(
        "chronological_order",
        "creation_reason",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "expiration_date",
        "expiration_time_zone",
        "last_modified_date",
        "last_modified_time_zone",
        "last_modified_by",
        "folder_name",
        "id",
        "custom_id"
    ),
        FOLDER_STATUS = array(
        "account_id",
        "creation_reason",
        "creation_date",
        "creation_time_zone",
        "open",
        "id"
    ),
        FOLDER_STATUS_SIMPLE = array(
        "account_id",
        "creation_reason",
        "creation_date",
        "creation_time_zone",
        "id"
    ),
        FOLDER_PARTICIPANT = array(
        "account_id",
        "creation_reason",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "id"
    ),
        HISTORY_REPLY = array(
        "history_value",
        "history_value_positions",
        "task_id"
    ),
        NOTIFICATION = array(
        "id",
        "translate_message",
        "notification_message",
        "platform_id",
        "platform_user_id",
        "platform_message_id",
        "platform_chat_id",
        "platform_chat_name",
        "notification_attachment_name",
        "notification_attachment_content",
        "notification_attachment_url",
        "notification_attachment_format",
        "is_base64",
        "is_video",
        "is_image",
        "is_audio",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "delete_on_delivery"
    ),
        NOTIFICATION_FULL = array(
        "id",
        "translate_message",
        "notification_message",
        "platform_id",
        "platform_user_id",
        "platform_message_id",
        "platform_chat_id",
        "platform_chat_name",
        "notification_attachment_name",
        "notification_attachment_content",
        "notification_attachment_url",
        "notification_attachment_format",
        "is_base64",
        "is_video",
        "is_image",
        "is_audio",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "team_id",
        "account_id",
        "delete_on_delivery"
    ),
        ATTACHMENT = array(
        "id",
        "attachment_format",
        "attachment_name",
        "attachment_bytes",
        "attachment_x",
        "attachment_y",
        "attachment_data",
        "attachment_data_hash",
        "is_base64",
        "analyzed_description",
        "analyzed_description_positions",
        "creation_date",
        "creation_time_zone",
        "created_by"
    ),
        CREATED_ATTACHMENT = array(
        "id",
        "attachment_format",
        "attachment_name",
        "attachment_bytes",
        "attachment_x",
        "attachment_y",
        "attachment_data",
        "attachment_data_hash",
        "is_base64",
        "analyzed_description",
        "analyzed_description_positions",
        "user_prompt",
        "creation_date",
        "creation_time_zone",
        "created_by"
    ),
        PARTIAL_ATTACHMENT = array(
        "id",
        "attachment_format",
        "attachment_name",
        "attachment_bytes",
        "attachment_x",
        "attachment_y",
        "analyzed_description",
        "analyzed_description_positions",
        "attachment_data_hash",
        "creation_date",
        "creation_time_zone",
        "created_by"
    ),
        CREATED_PARTIAL_ATTACHMENT = array(
        "id",
        "attachment_format",
        "attachment_name",
        "attachment_bytes",
        "attachment_x",
        "attachment_y",
        "analyzed_description",
        "analyzed_description_positions",
        "attachment_data_hash",
        "user_prompt",
        "creation_date",
        "creation_time_zone",
        "created_by"
    ),
        HISTORY = array(
        "id",
        "platform_id",
        "platform_user_id",
        "platform_message_id",
        "platform_chat_id",
        "platform_chat_name",
        "account_id",
        "creation_date",
        "creation_time_zone"
    ),
        WEB_DATA = array(
        "query",
        "results"
    ),
        PORTAL_HISTORY = array(
        "id",
        "message",
        "creation_date",
        "ip_address",
        "link_url",
        "account_id",
        "user_nick_name",
        "user_display_name",
        "user_id",
        "link_analyzed_description",
        "attachment_format",
        "attachment_name",
        "attachment_bytes",
        "attachment_x",
        "attachment_y",
        "attachment_data",
        "attachment_analyzed_description"
    ),
        PORTAL_HISTORY_PENDING = array(
        "id",
        "portal_id",
        "session_id",
        "default_language",
        "platform_id",
        "server_id",
        "channel_id",
        "channel_thread_id",
        "message_id",
        "user_id"
    ),
        PORTAL_HISTORY_TRIGGER = array(
        "id",
        "message"
    ),
        INSTALLED_PORTAL_MESSAGE = array(
        "id",
        "portal_id",
        "platform_id",
        "server_id",
        "channel_id",
        "channel_thread_id",
        "message_id",
        "user_id",
        "message",
        "creation_date",
        "attachment_format",
        "attachment_name",
        "attachment_bytes",
        "attachment_x",
        "attachment_y",
        "attachment_data",
        "attachment_analyzed_description"
    ),
        HISTORY_PROMPT = array(
        "id",
        "history_value"
    ),
        HISTORY_PROMPT_POSITIONS = array(
        "id",
        "history_value_positions"
    ),
        HISTORY_LINK = array(
        "id",
        "link_url",
        "link_content",
        "analyzed_description",
        "analyzed_description_positions",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "refresh_date",
        "refresh_time_zone",
        "refreshed_by"
    ),
        HISTORY_LINK_PARTIAL = array(
        "id",
        "link_url",
        "analyzed_description",
        "analyzed_description_positions",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "refresh_date",
        "refresh_time_zone",
        "refreshed_by"
    ),
        PORTAL_LINK = array(
        "id",
        "link_url",
        "link_content",
        "analyzed_description",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "refresh_date",
        "refresh_time_zone",
        "refreshed_by"
    ),
        PORTAL_LINK_PARTIAL = array(
        "id",
        "link_url",
        "analyzed_description",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "refresh_date",
        "refresh_time_zone",
        "refreshed_by"
    ),
        PAYPAL_EMAIL = array(
        "id",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "email_address"
    ),
        PORTAL = array(
        "id",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "random_id",
        "name",
        "downloads_allowed",
        "is_18_plus",
        "require_login",
        "password",
        "voice_replies",
        "skip_robot_verification",
        "default_message",
        "default_url",
        "transfer_to"
    ),
        PORTAL_WITH_TEAM = array(
        "id",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "random_id",
        "name",
        "downloads_allowed",
        "is_18_plus",
        "require_login",
        "password",
        "team_id",
        "voice_replies",
        "skip_robot_verification",
        "default_message",
        "default_url",
        "transfer_to"
    ),
        SCHEDULER = array(
        "id",
        "creation_date",
        "creation_time_zone",
        "creation_reason",
        "account_id",
        "month",
        "day",
        "hour",
        "minute",
        "next_date",
        "seconds_frequency",
        "history_id"
    ),
        SCHEDULER_WITH_TEAM_ID = array(
        "id",
        "creation_date",
        "creation_time_zone",
        "creation_reason",
        "account_id",
        "hour",
        "minute",
        "next_date",
        "seconds_frequency",
        "team_id",
        "history_id"
    ),
        INSTRUCTION = array(
        "id",
        "creation_date",
        "creation_time_zone",
        "created_by",
        "instruction"
    ),
        TIME_ACCESS = array(
        "id",
        "creation_date",
        "created_by",
        "creation_time_zone",
        "creation_reason",
        "start_hour",
        "end_hour",
        "start_minutes",
        "end_minutes"
    ),
        WEEK_ACCESS = array(
        "id",
        "creation_date",
        "created_by",
        "creation_time_zone",
        "creation_reason",
        "monday_access",
        "tuesday_access",
        "wednesday_access",
        "thursday_access",
        "friday_access",
        "saturday_access",
        "sunday_access"
    );

}

class IdealisticOfficeTable implements PhpAsyncSerializable
{
    public const
        AI_REQUESTS_TABLE = "idealistic.aiRequests",

        SCHEDULER_TABLE = "idealistic.scheduler",

        ERRORS_TABLE = "idealistic.errors",

        ENABLED_TASKS_TABLE = "idealistic.enabledTasks",

        GMAIL_QUEUE_TABLE = "idealistic.gmailQueue",
        GMAIL_TRACKING_TABLE = "idealistic.gmailTracking",

        PAYPAL_EMAILS_TABLE = "idealistic.paypalEmails",
        PAYPAL_PROCESSED_TRANSACTIONS_TABLE = "idealistic.paypalProcessedTransactions",

        NOTIFICATIONS_TABLE = "idealistic.notifications",
        LATE_NOTIFICATIONS_TABLE = "idealistic.lateNotifications",

        FOLDERS_TABLE = "idealistic.folders",
        FOLDER_STATUS_TABLE = "idealistic.folderStatus",
        FOLDER_PARTICIPANTS_TABLE = "idealistic.folderParticipants",
        FOLDER_COMMENTS_TABLE = "idealistic.folderComments",
        FOLDER_ATTACHED_PORTALS_TABLE = "idealistic.folderAttachedPortals",

        PORTALS_TABLE = "idealistic.portals",
        PORTAL_ACCESS_TABLE = "idealistic.portalAccess",
        PORTAL_HISTORY_TABLE = "idealistic.portalHistory",
        PORTAL_LINKS_TABLE = "idealistic.portalLinks",
        PORTAL_INSTALLATIONS_TABLE = "idealistic.portalInstallations",

        LEGAL_RECORDS_TABLE = "idealistic.legalRecords",

        HISTORY_TOTAL_TABLE = "idealistic.historyTotal",
        HISTORY_PROCESSING_TIMES_TABLE = "idealistic.historyProcessingTimes",
        HISTORY_REPLIES_TABLE = "idealistic.historyReplies",
        HISTORY_CREATED_ATTACHMENTS_TABLE = "idealistic.historyCreatedAttachments",
        HISTORY_PROMPTS_TABLE = "idealistic.historyPrompts",
        HISTORY_LINKS_TABLE = "idealistic.historyLinks",
        HISTORY_USER_ATTACHMENTS_TABLE = "idealistic.historyUserAttachments",
        HISTORY_ATTACHMENT_ACCESS_TABLE = "idealistic.historyAttachmentAccess",
        HISTORY_FAILED_OBJECTS_TABLE = "idealistic.historyFailedObjects",
        HISTORY_WEB_DATA_TABLE = "idealistic.historyWebData",

        USER_IDENTITIES_TABLE = "idealistic.userIdentities",
        USER_TIME_ACCESS_TABLE = "idealistic.userTimeAccess",
        USER_WEEK_ACCESS_TABLE = "idealistic.userWeeklyAccess",
        USER_SHORTCUTS_TABLE = "idealistic.userShortcuts",
        USER_SELECTED_FOLDER_TABLE = "idealistic.userSelectedFolder",

        TEAM_ADMIN_BLOCKED_ACCESS = "idealistic.teamAdminBlockedAccess",
        TEAM_TIME_ACCESS_TABLE = "idealistic.teamTimeAccess",
        TEAM_INSTRUCTIONS_TABLE = "idealistic.teamInstructions",
        TEAM_CREDITS_TABLE = "idealistic.teamCredits",
        TEAM_CREDIT_INCLUDED_MEMBERS_TABLE = "idealistic.teamCreditIncludedMembers",
        TEAM_CREDIT_CACHE_TABLE = "idealistic.teamCreditCache",
        TEAM_UNLIMITED_CREDITS_TABLE = "idealistic.teamUnlimitedCredits",
        TEAM_CREDIT_REFILL_TABLE = "idealistic.teamCreditRefill",
        TEAM_WEEKLY_ACCESS_TABLE = "idealistic.teamWeeklyAccess",
        TEAM_INCLUDED_MESSAGES_TABLE = "idealistic.teamIncludedMessages",
        TEAM_INCLUDED_MESSAGES_TRACKING_TABLE = "idealistic.teamIncludedMessagesTracking",

        ROLE_TIME_ACCESS_TABLE = "idealistic.roleTimeAccess",
        ROLE_WEEK_ACCESS_TABLE = "idealistic.roleWeeklyAccess";

    public static function enableSqlLocalMemory(?int $accessPlatform = null): void
    {
        $array = array(
            IdealisticOfficeTable::ENABLED_TASKS_TABLE,
            IdealisticOfficeTable::LEGAL_RECORDS_TABLE,
            // Portal
            IdealisticOfficeTable::PORTALS_TABLE,
            IdealisticOfficeTable::PORTAL_ACCESS_TABLE,
            IdealisticOfficeTable::PORTAL_HISTORY_TABLE,
            IdealisticOfficeTable::PORTAL_LINKS_TABLE,
            IdealisticOfficeTable::PORTAL_INSTALLATIONS_TABLE,
            // Team
            IdealisticOfficeTable::TEAM_ADMIN_BLOCKED_ACCESS,
            IdealisticOfficeTable::TEAM_TIME_ACCESS_TABLE,
            IdealisticOfficeTable::TEAM_INSTRUCTIONS_TABLE,
            IdealisticOfficeTable::TEAM_CREDITS_TABLE,
            IdealisticOfficeTable::TEAM_CREDIT_INCLUDED_MEMBERS_TABLE,
            IdealisticOfficeTable::TEAM_CREDIT_CACHE_TABLE,
            IdealisticOfficeTable::TEAM_UNLIMITED_CREDITS_TABLE,
            IdealisticOfficeTable::TEAM_CREDIT_REFILL_TABLE,
            IdealisticOfficeTable::TEAM_WEEKLY_ACCESS_TABLE,
            IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TABLE,
            IdealisticOfficeTable::TEAM_INCLUDED_MESSAGES_TRACKING_TABLE,
            // Role
            IdealisticOfficeTable::ROLE_TIME_ACCESS_TABLE,
            IdealisticOfficeTable::ROLE_WEEK_ACCESS_TABLE,
            // Member
            IdealisticOfficeTable::USER_IDENTITIES_TABLE,
            IdealisticOfficeTable::USER_TIME_ACCESS_TABLE,
            IdealisticOfficeTable::USER_WEEK_ACCESS_TABLE,
            IdealisticOfficeTable::USER_SHORTCUTS_TABLE,
            IdealisticOfficeTable::USER_SELECTED_FOLDER_TABLE,
            // Folder
            IdealisticOfficeTable::FOLDERS_TABLE,
            IdealisticOfficeTable::FOLDER_STATUS_TABLE,
            IdealisticOfficeTable::FOLDER_PARTICIPANTS_TABLE,
            IdealisticOfficeTable::FOLDER_COMMENTS_TABLE,
            IdealisticOfficeTable::FOLDER_ATTACHED_PORTALS_TABLE,
            // History
            IdealisticOfficeTable::HISTORY_TOTAL_TABLE,
            // PayPal
            IdealisticOfficeTable::PAYPAL_EMAILS_TABLE,
            IdealisticOfficeTable::PAYPAL_PROCESSED_TRANSACTIONS_TABLE,
            // Errors
            IdealisticOfficeTable::ERRORS_TABLE,
            // Scheduler
            IdealisticOfficeTable::SCHEDULER_TABLE,
            // AI Requests
            IdealisticOfficeTable::AI_REQUESTS_TABLE,
            IdealisticOfficeTable::HISTORY_PROCESSING_TIMES_TABLE,
            IdealisticOfficeTable::HISTORY_CREATED_ATTACHMENTS_TABLE,
            IdealisticOfficeTable::HISTORY_PROMPTS_TABLE,
            IdealisticOfficeTable::HISTORY_LINKS_TABLE,
            IdealisticOfficeTable::HISTORY_USER_ATTACHMENTS_TABLE,
            IdealisticOfficeTable::HISTORY_ATTACHMENT_ACCESS_TABLE,
            IdealisticOfficeTable::HISTORY_WEB_DATA_TABLE,
            // External #1
            AccountVariables::TEAM_MEMBERS_TABLE,
            AccountVariables::TEAM_TABLE,
            AccountVariables::TEAM_OWNERS_TABLE,
            AccountVariables::TEAM_PERMISSIONS_TABLE,
            AccountVariables::TEAM_MEMBER_PERMISSIONS_TABLE,
            AccountVariables::TEAM_PERMISSION_DEFINITIONS_TABLE,
            AccountVariables::TEAM_NAME_CHANGES,
            AccountVariables::TEAM_ROLE_NAME_CHANGES,
            AccountVariables::TEAM_MEMBER_POSITIONS_TABLE,
            AccountVariables::TEAM_ROLES_TABLE,
            AccountVariables::TEAM_ROLE_MEMBERS_TABLE,
            AccountVariables::TEAM_ROLE_POSITIONS_TABLE,
            AccountVariables::TEAM_ROLE_PERMISSIONS_TABLE,
            // External #2
            AccountVariables::TRANSLATIONS_PROCESSED_TABLE,
            AccountVariables::EMBEDDINGS_PROCESSED_TABLE,
            AccountVariables::ACCOUNTS_TABLE,
            AccountVariables::ACCOUNT_SETTINGS_TABLE,
            AccountVariables::ACCOUNT_INSTANT_COOLDOWNS_TABLE,
            AccountVariables::ACCOUNT_BUFFER_COOLDOWNS_TABLE,
            AccountVariables::FUNCTIONALITIES_TABLE,
            AccountVariables::BLOCKED_FUNCTIONALITIES_TABLE,
            AccountVariables::ADDED_ACCOUNTS_TABLE,
            AccountVariables::ACCEPTED_ACCOUNTS_TABLE,
            AccountVariables::TWO_FACTOR_AUTHENTICATION_TABLE,
            AccountVariables::ACCOUNT_PERMISSIONS_TABLE,
            AccountVariables::ACCOUNT_ROLES_TABLE,
            AccountVariables::ROLES_TABLE,
            AccountVariables::ROLE_PERMISSIONS_TABLE
        );

        if ($accessPlatform !== IdealisticOfficeAccessPlatform::MESSENGER) { // silentlyFail()
            $array[] = IdealisticOfficeTable::HISTORY_REPLIES_TABLE;
        }
        sql_set_local_memory($array);
    }
}

class IdealisticOfficePermission implements PhpAsyncSerializable
{
    public const
        // Permissions
        ADJUST_TEAM_PERMISSIONS = AccountTeam::PERMISSION_ADJUST_TEAM_PERMISSIONS,
        ADJUST_USER_PERMISSIONS = AccountTeam::PERMISSION_ADJUST_TEAM_MEMBER_PERMISSIONS,
        ADJUST_ROLE_PERMISSIONS = AccountTeam::PERMISSION_ADJUST_TEAM_ROLE_PERMISSIONS,

        // Additions
        CREATE_USERS = AccountTeam::PERMISSION_ADD_TEAM_MEMBERS,
        CREATE_ROLES = AccountTeam::PERMISSION_CREATE_TEAM_ROLES,

        // Deletions
        DELETE_USERS = AccountTeam::PERMISSION_REMOVE_TEAM_MEMBERS,
        DELETE_ROLES = AccountTeam::PERMISSION_DELETE_TEAM_ROLES,
        RESTORE_ROLES = AccountTeam::PERMISSION_RESTORE_TEAM_ROLES,

        // General Access
        ADJUST_USER_GENERAL_ACCESS = 25,
        ADJUST_ROLE_GENERAL_ACCESS = 23,
        ADJUST_TEAM_GENERAL_ACCESS = 21,

        // Time Access
        ADJUST_USER_TIME_ACCESS = 26,
        ADJUST_ROLE_TIME_ACCESS = 24,
        ADJUST_TEAM_TIME_ACCESS = 22,

        // Modifications
        ADJUST_USER_ROLES = AccountTeam::PERMISSION_ADJUST_TEAM_MEMBER_ROLES,
        ADJUST_USER_HIERARCHY = AccountTeam::PERMISSION_ADJUST_TEAM_MEMBER_POSITIONS,
        ADJUST_ROLE_HIERARCHY = AccountTeam::PERMISSION_ADJUST_TEAM_ROLE_POSITIONS,

        // Naming
        ADJUST_TEAM_NAME = AccountTeam::PERMISSION_CHANGE_TEAM_NAME,
        ADJUST_ROLE_NAME = AccountTeam::PERMISSION_CHANGE_TEAM_ROLE_NAMES,
        ADJUST_TEAM_DESCRIPTION = AccountTeam::PERMISSION_CHANGE_TEAM_DESCRIPTION,
        ADJUST_ROLE_DESCRIPTION = AccountTeam::PERMISSION_CHANGE_TEAM_ROLE_DESCRIPTIONS,

        // Language
        ADJUST_USER_LANGUAGE_SELECTION = 35,

        // Timezones
        ADJUST_USER_TIMEZONE = 33,

        // Folder
        ADJUST_FOLDERS = 36,
        PARTICIPATE_IN_FOLDERS = 37,

        // Instructions
        ADJUST_TEAM_INSTRUCTIONS = 40,

        // Paypal Emails
        ADJUST_TEAM_PAYPAL_EMAILS = 63,

        // Week Access
        ADJUST_USER_WEEK_ACCESS = 71,
        ADJUST_ROLE_WEEK_ACCESS = 73,
        ADJUST_TEAM_WEEK_ACCESS = 72,

        // Shop
        ADJUST_PORTALS = 85,

        // Scheduler
        ADJUST_SCHEDULER = 92;
}