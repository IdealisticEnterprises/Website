<?php
// Dependencies
require_once '/var/www/.structure/library/base/vendor.php';
require_once '/var/www/.structure/library/base/utilities.php';
require_once '/var/www/.structure/library/base/react.php';
require_once '/var/www/.structure/library/base/form.php';
require_once '/var/www/.structure/library/base/cookies.php';
require_once '/var/www/.structure/library/base/audio.php';
require_once '/var/www/.structure/library/base/sql.php';
require_once '/var/www/.structure/library/base/statistics.php';
require_once '/var/www/.structure/library/base/async.php';
require_once '/var/www/.structure/library/base/placeholder.php';
require_once '/var/www/.structure/library/base/vector.php';
require_once '/var/www/.structure/library/base/encrypt.php';
require_once '/var/www/.structure/library/base/qrcode.php';
require_once '/var/www/.structure/library/base/objects/AbstractMethodReply.php';

require_once '/var/www/.structure/library/memory/init.php';
require_once '/var/www/.structure/library/email/init.php';
require_once '/var/www/.structure/library/phone/init.php';
require_once '/var/www/.structure/library/account/init.php';
require_once '/var/www/.structure/library/patreon/init.php';
require_once '/var/www/.structure/library/paypal/init.php';
require_once '/var/www/.structure/library/ai/init.php';
require_once '/var/www/.structure/library/google/init.php';

// Local
require_once '/var/www/.structure/library/idealistic_office/api/variables.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeJSON.php';

require_once '/var/www/.structure/library/idealistic_office/api/interfaces/IdealisticOfficeParticipant.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/AbstractIdealisticOfficeTeam.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/AbstractIdealisticOfficeChange.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/AbstractIdealisticOfficeInformationChange.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/timeAccess/IdealisticOfficePromptManageTimeAccesses.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/weeklyAccess/IdealisticOfficePromptManageWeeklyAccesses.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/instruction/IdealisticOfficePromptManageInstructions.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/paypalEmail/IdealisticOfficePromptManagePaypalEmails.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/creativity/IdealisticOfficePromptCreateImage.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/creativity/IdealisticOfficePromptCreateFile.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/creativity/IdealisticOfficePromptModifyFile.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/setting/IdealisticOfficePromptVoiceReplySetting.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/setting/IdealisticOfficePromptUserTimeZone.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/setting/IdealisticOfficePromptUserLanguageSelection.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/scheduler/IdealisticOfficePromptManageScheduler.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/portal/IdealisticOfficePromptManagePortals.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/attachment/IdealisticOfficePromptAttachmentAnalysis.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/attachment/IdealisticOfficePromptAttachmentPositions.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/attachment/IdealisticOfficePromptAttachmentNames.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/research/IdealisticOfficePromptMentionedUsers.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/research/IdealisticOfficePromptNewUsers.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/research/IdealisticOfficePromptWebData.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/link/IdealisticOfficePromptLinkAnalysis.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/link/IdealisticOfficePromptLinkPositions.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/general/IdealisticOfficePromptAccessCheck.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/general/IdealisticOfficePromptInformationAnalysis.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/general/IdealisticOfficePromptInformationAnalysisPositions.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/general/IdealisticOfficePromptGeneralVoiceReply.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/global/IdealisticOfficePromptManageShortcuts.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/team/IdealisticOfficePromptTeamInfo.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/team/IdealisticOfficePromptManageIncludedCreditMembers.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/team/IdealisticOfficePromptDataRetention.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/role/IdealisticOfficePromptManageRoles.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/folder/IdealisticOfficePromptManageFolders.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/prompts/folder/IdealisticOfficePromptShareInformation.php';

require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficeCombinedScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficeHistoryWebhookNotificationScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficeTransactionScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficeHistoryGmailScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficePortalScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficeOldTeamsScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/tasks/schedulers/combined/IdealisticOfficeHistorySchedulersScheduler.php';

require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeEmailer.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticPaddleApi.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeGmail.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeGmailRunner.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeMeta.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeWhatsApp.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeAsync.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeHistoryIndependent.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeReader.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeProcessor.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeStrings.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeError.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeNotifications.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeTeamInitiator.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeTransaction.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeWebsiteHTML.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeAttachmentSearch.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeLinkSearch.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficePortalIndependent.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeStructure.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeChat.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeBrand.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeLegal.php';
require_once '/var/www/.structure/library/idealistic_office/api/handlers/IdealisticOfficeAccountExporter.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/attachment/AbstractIdealisticOfficeAttachment.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/attachment/IdealisticOfficePartialAttachment.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/attachment/IdealisticOfficeAttachment.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficeAbstractParticipant.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficeAccess.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficeTimeAccess.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficeWeeklyAccess.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficePaypalEmail.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficeInstruction.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/IdealisticOfficeLegalRecord.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/folder/IdealisticOfficeFolderModifier.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/folder/IdealisticOfficeFolderParticipant.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/folder/IdealisticOfficeFolderComment.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/folder/IdealisticOfficeFolderAttachedPortal.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/change/folder/IdealisticOfficeFolderProgress.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/user/IdealisticOfficeUserIdentity.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/user/IdealisticOfficeUserPersonal.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/user/IdealisticOfficeUserShortcut.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/history/IdealisticOfficeHistoryReply.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/history/IdealisticOfficeHistoryWebData.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeCredit.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficePromptCache.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeLink.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeAdminBlockedAccess.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeNotification.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficePortalMessage.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeAI.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeOutcome.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeLanguageSelection.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficeTimezone.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/IdealisticOfficePortalStatistics.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/manager/IdealisticOfficeRoleManager.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/manager/IdealisticOfficeTimeAccessManager.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/manager/IdealisticOfficeWeeklyAccessManager.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/manager/IdealisticOfficeInstructionsManager.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/helpers/manager/IdealisticOfficePaypalEmailManager.php';

require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficeFolder.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficeTeam.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficeHistory.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficeRole.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficeUser.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficePrompt.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficeScheduler.php';
require_once '/var/www/.structure/library/idealistic_office/api/objects/IdealisticOfficePortal.php';
