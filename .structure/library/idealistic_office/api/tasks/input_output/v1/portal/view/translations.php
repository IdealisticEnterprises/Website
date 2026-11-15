<?php
if (!isset($appName)) {
    throw new RuntimeException('translations.php requires $appName to be set before it is included.');
}

$languages = [
    'english' => '🇬🇧', 'greek' => '🇬🇷', 'spanish' => '🇪🇸', 'french' => '🇫🇷',
    'german' => '🇩🇪', 'italian' => '🇮🇹', 'portuguese' => '🇵🇹', 'dutch' => '🇳🇱', 'estonian' => '🇪🇪',
    'polish' => '🇵🇱', 'croatian' => '🇭🇷', 'danish' => '🇩🇰', 'hungarian' => '🇭🇺', 'latvian' => '🇱🇻',
    'romanian' => '🇷🇴', 'slovak' => '🇸🇰', 'swedish' => '🇸🇪', 'finnish' => '🇫🇮', 'bulgarian' => '🇧🇬',
    'turkish' => '🇹🇷', 'lithuanian' => '🇱🇹', 'maltese' => '🇲🇹', 'czech' => '🇨🇿'
];

$translations = [
    'english' => [

        'meta_description' => (IdealisticOfficeVariable::APPLICATION_NAME . ' is the way for humans and AI to communicate in harmony. Add it to Discord, Telegram, WhatsApp or your website: humans talk, the AI keeps track, and it only speaks when asked.'),
        'meta_keywords' => ('AI, communication, Discord, Telegram, WhatsApp, chat widget, ' . IdealisticOfficeVariable::APPLICATION_NAME . ', group chat, conversation tracking, dispute resolution'),
        'og_description' => (IdealisticOfficeVariable::APPLICATION_NAME . ' is the way for humans and AI to communicate in harmony. Humans talk, the AI keeps track, and it only speaks when asked.'),

        'platforms_title' => ('Configure your ' . IdealisticOfficeVariable::APPLICATION_NAME . ' through your browser or connect your email address with your favorite apps and configure it from them'),

        'f_portal_step_1' => ('Sign in to ' . IdealisticOfficeVariable::APPLICATION_NAME),
        'f_portal_step_2' => 'Copy the one-line portal code',
        'f_portal_step_3' => 'Paste the one-line portal code on your website',
        'f_portal_step_done' => 'DONE! It is that simple',
        'f_portal_try_ph' => 'Enter your website URL...',
        'f_portal_try_btn' => 'Try',

        'features_title' => 'Collaborate with others seamlessly',
        'f_2' => 'TAGS',
        'f_2_desc' => "You have a username, and we made it for you! If your email were <span class='text-primary fw-semibold'>george23@idealistic.ai</span>, your username would be <span class='badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1'>george23</span>. People can tag you with it, for example: <span class='text-info fst-italic'>@george23 I updated our portal</span>",
        'f_3' => 'FILES',
        'f_3_desc' => (IdealisticOfficeVariable::APPLICATION_NAME . " does not just store your files; it understands them. Mention anything related to a previous document, and it will retrieve it instantly for your convenience."),
        'f_5' => 'INSTRUCTIONS',
        'f_5_desc' => ('Adjust the behavior of your ' . IdealisticOfficeVariable::APPLICATION_NAME . ' using your own language.'),
        'f_6' => 'SCHEDULE',
        'f_6_desc' => 'Create automated prompts that run exactly when you choose, just like writing one at that moment. Manage routines and broadcast updates effortlessly.',
        'f_8' => 'BLOCK',
        'f_8_desc' => 'Control who works and when on your shared portals. Block access completely or restrict it to a specific day, hour and minute.',

        'def_msg_main' => 'How can you help me?',

        'ft_terms' => 'Terms',
        'ft_privacy' => 'Privacy',
        'ft_registry' => 'Registry',
        'ft_structure' => 'Structure',

        'action_session_reset' => 'The session was reset successfully.',
        'action_signed_out' => 'You have signed out successfully.',
        'action_signed_in' => 'A verification email has been sent. Please check your inbox, including your spam folder.',
        'action_failed_sign_in' => 'Sign-in failed.',

        'btn_del_hist' => 'Delete History',
        'btn_sign_out' => 'Sign Out',
        'btn_sign_in' => 'Sign In',

        'f_fullname' => 'Full name',
        'f_email' => 'Email address',
        'f_password_ph' => 'Enter your password...',
        'f_cancel' => 'Cancel',
        'f_submit' => 'Submit',

        'signin_desc' => 'Enter your details to sign in.',
        'signin_terms' => "By signing in, you agree to our <a href='/terms/terms_of_use/' target='_blank' class='custom-link-color text-decoration-none'>Terms of Use</a> and <a href='/policies/privacy_policy/' target='_blank' class='custom-link-color text-decoration-none'>Privacy Policy</a>.",
        'demo_account_hint' => 'This is a demo account.',
        'welcome_back' => 'Welcome back, ',


        'anim_widget' => 'is where humans talk',
        'anim_chatbot' => 'is AI that keeps track',
        'anim_chatbox' => 'only speaks when asked',
        'anim_support' => 'settles disputes',
        'anim_clients' => 'resolves disagreements',
        'anim_business' => 'brings your chat back to life',
        'anim_modern' => 'works on Discord, Telegram, WhatsApp and the web',

        'auth_captcha_failed' => 'Captcha verification failed. Please prove that you are human.',
        'auth_age_confirmation' => 'I confirm that I am 18 years of age or older.',
        'auth_unlock' => 'Unlock',
        'auth_enter' => 'Enter',


        'chat_message_ph' => 'Enter your message...',
        'chat_send' => 'SEND',
        'chat_recording' => 'Recording... tap to stop',
        'chat_microphone_error' => 'Microphone access was denied or the microphone is unavailable.',
        'chat_wait_reply' => 'Wait for the reply...',
        'chat_voice_ready' => 'Voice message ready to send...',
        'chat_file' => 'File',
        'chat_voice_message' => 'Voice Message',
        'chat_audio' => 'Audio',
        'chat_network_failed' => 'The network request failed.',

        'confirm_clear_history' => 'Are you sure you want to clear your chat history? This action cannot be undone.',
        'confirm_sign_out' => 'Are you sure you want to sign out?',
        'close' => 'Close'
    ],
];

return [
    'languages' => $languages,
    'translations' => $translations,
];