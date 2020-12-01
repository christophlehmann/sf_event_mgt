<?php
namespace DERHANSEN\SfEventMgt\Service;

/*
 * This file is part of the Extension "sf_event_mgt" for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use DERHANSEN\SfEventMgt\Domain\Model\Dto\CustomNotification;
use DERHANSEN\SfEventMgt\Domain\Model\Event;
use DERHANSEN\SfEventMgt\Utility\MessageRecipient;
use DERHANSEN\SfEventMgt\Utility\MessageType;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;

/**
 * NotificationService
 *
 * @author Torben Hansen <derhansen@gmail.com>
 */
class NotificationService
{
    /**
     * The object manager
     *
     * @var \TYPO3\CMS\Extbase\Object\ObjectManager
     */
    protected $objectManager;

    /**
     * Registration repository
     *
     * @var \DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository
     */
    protected $registrationRepository = null;

    /**
     * Email Service
     *
     * @var \DERHANSEN\SfEventMgt\Service\EmailService
     */
    protected $emailService;

    /**
     * Hash Service
     *
     * @var \TYPO3\CMS\Extbase\Security\Cryptography\HashService
     */
    protected $hashService;

    /**
     * FluidStandaloneService
     *
     * @var \DERHANSEN\SfEventMgt\Service\FluidStandaloneService
     */
    protected $fluidStandaloneService;

    /**
     * CustomNotificationLogRepository
     *
     * @var \DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository
     */
    protected $customNotificationLogRepository = null;

    /**
     * AttachmentService
     *
     * @var \DERHANSEN\SfEventMgt\Service\Notification\AttachmentService
     */
    protected $attachmentService;

    /**
     * @var \TYPO3\CMS\Extbase\SignalSlot\Dispatcher
     */
    protected $signalSlotDispatcher = null;

    /**
     * DI for $attachmentService
     *
     * @param Notification\AttachmentService $attachmentService
     */
    public function injectAttachmentService(
        \DERHANSEN\SfEventMgt\Service\Notification\AttachmentService $attachmentService
    ) {
        $this->attachmentService = $attachmentService;
    }

    /**
     * DI for $customNotificationLogRepository
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository $customNotificationLogRepository
     */
    public function injectCustomNotificationLogRepository(
        \DERHANSEN\SfEventMgt\Domain\Repository\CustomNotificationLogRepository $customNotificationLogRepository
    ) {
        $this->customNotificationLogRepository = $customNotificationLogRepository;
    }

    /**
     * DI for $emailService
     *
     * @param EmailService $emailService
     */
    public function injectEmailService(\DERHANSEN\SfEventMgt\Service\EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * DI for $fluidStandaloneService
     *
     * @param FluidStandaloneService $fluidStandaloneService
     */
    public function injectFluidStandaloneService(
        \DERHANSEN\SfEventMgt\Service\FluidStandaloneService $fluidStandaloneService
    ) {
        $this->fluidStandaloneService = $fluidStandaloneService;
    }

    /**
     * DI for $hashService
     *
     * @param \TYPO3\CMS\Extbase\Security\Cryptography\HashService $hashService
     */
    public function injectHashService(\TYPO3\CMS\Extbase\Security\Cryptography\HashService $hashService)
    {
        $this->hashService = $hashService;
    }

    /**
     * DI for $objectManager
     *
     * @param \TYPO3\CMS\Extbase\Object\ObjectManager $objectManager
     */
    public function injectObjectManager(\TYPO3\CMS\Extbase\Object\ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * DI for $registrationRepository
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository $registrationRepository
     */
    public function injectRegistrationRepository(
        \DERHANSEN\SfEventMgt\Domain\Repository\RegistrationRepository $registrationRepository
    ) {
        $this->registrationRepository = $registrationRepository;
    }

    /**
     * DI for $signalSlotDispatcher
     *
     * @param Dispatcher $signalSlotDispatcher
     */
    public function injectSignalSlotDispatcher(\TYPO3\CMS\Extbase\SignalSlot\Dispatcher $signalSlotDispatcher)
    {
        $this->signalSlotDispatcher = $signalSlotDispatcher;
    }

    /**
     * Sends a custom notification defined by the given customNotification key
     * to all confirmed users of the event
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event
     * @param CustomNotification $customNotification
     * @param array $settings Settings
     *
     * @return int Number of notifications sent
     */
    public function sendCustomNotification(Event $event, CustomNotification $customNotification, array $settings = [])
    {
        if ($this->cantSendCustomNotification($event, $settings, $customNotification)) {
            return 0;
        }
        $count = 0;

        $customNotificationSettings = $settings['notification']['customNotifications'];
        $constraints = $customNotificationSettings[$customNotification->getTemplate()]['constraints'] ?? [];
        $registrations = $this->registrationRepository->findNotificationRegistrations(
            $event,
            $customNotification,
            $constraints
        );

        foreach ($registrations as $registration) {
            /** @var \DERHANSEN\SfEventMgt\Domain\Model\Registration $registration */
            if ($registration->isConfirmed() && !$registration->isIgnoreNotifications()) {
                $result = $this->sendUserMessage(
                    $event,
                    $registration,
                    $settings,
                    MessageType::CUSTOM_NOTIFICATION,
                    $customNotification
                );
                if ($result) {
                    $count += 1;
                }
            }
        }

        return $count;
    }

    /**
     * Returns true if conditions are not met to send a custom notification
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event
     * @param array $settings
     * @param CustomNotification $customNotification
     *
     * @return bool
     */
    protected function cantSendCustomNotification($event, $settings, $customNotification)
    {
        return is_null($event) || $customNotification === null || $customNotification->getTemplate() === '' ||
            empty($settings);
    }

    /**
     * Adds a logentry to the custom notification log
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event Event
     * @param string $details Details
     * @param int $emailsSent E-Mails sent
     *
     * @return void
     */
    public function createCustomNotificationLogentry($event, $details, $emailsSent)
    {
        $notificationlogEntry = new \DERHANSEN\SfEventMgt\Domain\Model\CustomNotificationLog();
        $notificationlogEntry->setPid($event->getPid());
        $notificationlogEntry->setEvent($event);
        $notificationlogEntry->setDetails($details);
        $notificationlogEntry->setEmailsSent($emailsSent);
        $notificationlogEntry->setCruserId($GLOBALS['BE_USER']->user['uid']);
        $this->customNotificationLogRepository->add($notificationlogEntry);
    }

    /**
     * Sends a message to the user based on the given type
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event Event
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Registration $registration Registration
     * @param array $settings Settings
     * @param int $type Type
     * @param CustomNotification $customNotification
     *
     * @return bool TRUE if successful, else FALSE
     */
    public function sendUserMessage($event, $registration, $settings, $type, $customNotification = null)
    {
        list($template, $subject) = $this->getUserMessageTemplateSubject(
            $settings,
            $type,
            $customNotification
        );

        if (is_null($event) || is_null($registration) || is_null($type) || !is_array($settings) ||
            (substr($template, -5) != '.html') || (bool)$settings['notification']['disabled']
        ) {
            return false;
        }

        $additionalBodyVariables = [
            'customNotification' => $customNotification
        ];

        if (!$registration->isIgnoreNotifications()) {
            $body = $this->getNotificationBody($event, $registration, $template, $settings, $additionalBodyVariables);
            $subject = $this->fluidStandaloneService->parseStringFluid(
                $subject,
                [
                    'event' => $event,
                    'registration' => $registration
                ]
            );
            $attachments = $this->attachmentService->getAttachments(
                $settings,
                $registration,
                $type,
                MessageRecipient::USER
            );

            // Get iCal attachment if configured
            $iCalAttachment = $this->attachmentService->getICalAttachment(
                $settings,
                $registration,
                $type,
                MessageRecipient::USER
            );

            if ($iCalAttachment !== '') {
                $attachments[] = $iCalAttachment;
            }

            $senderName = $settings['notification']['senderName'];
            $senderEmail = $settings['notification']['senderEmail'];
            $replyToEmail = $settings['notification']['replyToEmail'];

            $this->signalSlotDispatcher->dispatch(
                __CLASS__,
                __FUNCTION__ . 'CustomSenderData',
                [&$senderName, &$senderEmail, &$replyToEmail, $registration, $type, $this]
            );

            $this->signalSlotDispatcher->dispatch(
                __CLASS__,
                __FUNCTION__ . 'CustomAttachmentData',
                [&$attachments, $registration, $type, $this]
            );

            $result = $this->emailService->sendEmailMessage(
                $senderEmail,
                $registration->getEmail(),
                $subject,
                $body,
                $senderName,
                $attachments,
                $replyToEmail
            );

            $this->signalSlotDispatcher->dispatch(
                __CLASS__,
                __FUNCTION__ . 'AfterNotificationSent',
                [$registration, $body, $subject, $attachments, $senderName, $senderEmail, $replyToEmail, $this]
            );

            // Cleanup iCal attachment if available
            if ($iCalAttachment !== '') {
                \TYPO3\CMS\Core\Utility\GeneralUtility::unlink_tempfile($iCalAttachment);
            }

            return $result;
        }

        return false;
    }

    /**
     * Returns an array with template and subject for the user message
     *
     * @param array $settings
     * @param int $type Type
     * @param CustomNotification $customNotification
     * @return array
     */
    protected function getUserMessageTemplateSubject($settings, $type, $customNotification = null)
    {
        switch ($type) {
            case MessageType::REGISTRATION_NEW:
                $template = 'Notification/User/RegistrationNew.html';
                $subject = $settings['notification']['registrationNew']['userSubject'];
                break;
            case MessageType::REGISTRATION_WAITLIST_NEW:
                $template = 'Notification/User/RegistrationWaitlistNew.html';
                $subject = $settings['notification']['registrationWaitlistNew']['userSubject'];
                break;
            case MessageType::REGISTRATION_CONFIRMED:
                $template = 'Notification/User/RegistrationConfirmed.html';
                $subject = $settings['notification']['registrationConfirmed']['userSubject'];
                break;
            case MessageType::REGISTRATION_WAITLIST_CONFIRMED:
                $template = 'Notification/User/RegistrationWaitlistConfirmed.html';
                $subject = $settings['notification']['registrationWaitlistConfirmed']['userSubject'];
                break;
            case MessageType::REGISTRATION_CANCELLED:
                $template = 'Notification/User/RegistrationCancelled.html';
                $subject = $settings['notification']['registrationCancelled']['userSubject'];
                break;
            case MessageType::REGISTRATION_WAITLIST_MOVE_UP:
                $template = 'Notification/User/RegistrationWaitlistMoveUp.html';
                $subject = $settings['notification']['registrationWaitlistMoveUp']['userSubject'];
                break;
            case MessageType::CUSTOM_NOTIFICATION && $customNotification:
                $customNotificationSettings = $settings['notification']['customNotifications'];
                $templateKey = $customNotification->getTemplate();
                $template = 'Notification/User/Custom/' . $customNotificationSettings[$templateKey]['template'];
                $subject = $customNotificationSettings[$templateKey]['subject'];
                if ($customNotification->getOverwriteSubject() !== '') {
                    $subject = $customNotification->getOverwriteSubject();
                }
                break;
            default:
                $template = '';
                $subject = '';
        }

        return [
            $template,
            $subject
        ];
    }

    /**
     * Sends a message to the admin based on the given type
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event Event
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Registration $registration Registration
     * @param array $settings Settings
     * @param int $type Type
     *
     * @return bool TRUE if successful, else FALSE
     */
    public function sendAdminMessage($event, $registration, $settings, $type)
    {
        list($template, $subject) = $this->getAdminMessageTemplateSubject($settings, $type);

        if (is_null($event) || is_null($registration || !is_array($settings)) ||
            ($event->getNotifyAdmin() === false && $event->getNotifyOrganisator() === false)
        ) {
            return false;
        }

        $allEmailsSent = true;
        $body = $this->getNotificationBody($event, $registration, $template, $settings);
        $subject = $this->fluidStandaloneService->parseStringFluid(
            $subject,
            [
                'event' => $event,
                'registration' => $registration
            ]
        );
        $attachments = $this->attachmentService->getAttachments(
            $settings,
            $registration,
            $type,
            MessageRecipient::ADMIN
        );

        $senderName = $settings['notification']['senderName'];
        $senderEmail = $settings['notification']['senderEmail'];
        if ((bool)$settings['notification']['registrationDataAsSenderForAdminEmails']) {
            $senderName = $registration->getFullname();
            $senderEmail = $registration->getEmail();
        }

        if ($event->getNotifyAdmin()) {
            $adminEmailArr = GeneralUtility::trimExplode(',', $settings['notification']['adminEmail'], true);
            foreach ($adminEmailArr as $adminEmail) {
                $allEmailsSent = $allEmailsSent && $this->emailService->sendEmailMessage(
                    $senderEmail,
                    $adminEmail,
                    $subject,
                    $body,
                    $senderName,
                    $attachments
                );
            }
        }

        if ($event->getNotifyOrganisator() && $event->getOrganisator()) {
            $allEmailsSent = $allEmailsSent && $this->emailService->sendEmailMessage(
                $senderEmail,
                $event->getOrganisator()->getEmail(),
                $subject,
                $body,
                $senderName,
                $attachments
            );
        }

        $this->signalSlotDispatcher->dispatch(
            __CLASS__,
            __FUNCTION__ . 'AfterNotificationSent',
            [$registration, $body, $subject, $attachments, $senderName, $senderEmail, $this]
        );

        return $allEmailsSent;
    }

    /**
     * Returns an array with template and subject for the admin message
     *
     * @param array $settings
     * @param int $type Type
     * @return array
     */
    protected function getAdminMessageTemplateSubject($settings, $type)
    {
        $template = 'Notification/Admin/RegistrationNew.html';
        $subject = $settings['notification']['registrationNew']['adminSubject'];
        switch ($type) {
            case MessageType::REGISTRATION_WAITLIST_NEW:
                $template = 'Notification/Admin/RegistrationWaitlistNew.html';
                $subject = $settings['notification']['registrationWaitlistNew']['adminSubject'];
                break;
            case MessageType::REGISTRATION_CONFIRMED:
                $template = 'Notification/Admin/RegistrationConfirmed.html';
                $subject = $settings['notification']['registrationConfirmed']['adminSubject'];
                break;
            case MessageType::REGISTRATION_WAITLIST_CONFIRMED:
                $template = 'Notification/Admin/RegistrationWaitlistConfirmed.html';
                $subject = $settings['notification']['registrationWaitlistConfirmed']['adminSubject'];
                break;
            case MessageType::REGISTRATION_CANCELLED:
                $template = 'Notification/Admin/RegistrationCancelled.html';
                $subject = $settings['notification']['registrationCancelled']['adminSubject'];
                break;
            case MessageType::REGISTRATION_WAITLIST_MOVE_UP:
                $template = 'Notification/Admin/RegistrationWaitlistMoveUp.html';
                $subject = $settings['notification']['registrationWaitlistMoveUp']['adminSubject'];
                break;
            case MessageType::REGISTRATION_NEW:
            default:
        }

        return [
            $template,
            $subject
        ];
    }

    /**
     * Returns the rendered HTML for the given template
     *
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Event $event Event
     * @param \DERHANSEN\SfEventMgt\Domain\Model\Registration $registration Registration
     * @param string $template Template
     * @param array $settings Settings
     * @param array $additionalBodyVariables
     * @return string
     */
    protected function getNotificationBody($event, $registration, $template, $settings, $additionalBodyVariables = [])
    {
        if (TYPO3_MODE === 'BE' && $registration->getLanguage() !== '') {
            // Temporary set Language of current BE user to given language
            $GLOBALS['BE_USER']->uc['lang'] = $registration->getLanguage();
        }
        $defaultVariables = [
            'event' => $event,
            'registration' => $registration,
            'settings' => $settings,
            'hmac' => $this->hashService->generateHmac('reg-' . $registration->getUid()),
            'reghmac' => $this->hashService->appendHmac((string)$registration->getUid())
        ];
        $variables = array_merge($additionalBodyVariables, $defaultVariables);

        return $this->fluidStandaloneService->renderTemplate($template, $variables);
    }
}
