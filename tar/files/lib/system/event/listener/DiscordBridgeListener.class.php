<?php

namespace wbb\system\event\listener;

use wcf\system\WCF;
use wcf\data\user\User;
use wcf\data\user\UserProfile;
use wcf\system\discord\Webhook;
use wcf\system\html\output\HtmlOutputProcessor;
use wcf\system\event\listener\IParameterizedEventListener;

class DiscordBridgeListener implements IParameterizedEventListener {

    /**
     * @see \wcf\system\event\listener\IParameterizedEventListener::execute()
     */
    public function execute($eventObj, $className, $eventName, array &$parameters) {
        if(DISCORD_WBB_BRIDGE_WEBHOOK_ID == '' && DISCORD_WBB_BRIDGE_WEBHOOK_TOKEN == ''){
            return;
        }

        switch ($eventObj->getActionName()) {
            case 'triggerPublication':
                $objects = $eventObj->getObjects();
                if (empty($objects)) {
                    return;
                }
                $postData = $objects[0];
                $thread = $postData->getThread();

                if(in_array($thread->boardID, explode("\n", DISCORD_WBB_BRIDGE_IGNORE_BOARDS))){
                    return;
                }

                $messageProcessor = new HtmlOutputProcessor();
                $messageProcessor->setOutputType('text/plain');
                $messageProcessor->process($postData->message, 'com.woltlab.wbb.post', $postData->postID);
                $message = str_replace(["\n", "\r"], ' ', $messageProcessor->getHtml());

                $user = new User($postData->userID);
                $userProfile = new UserProfile($user);

                $webhook = new Webhook(DISCORD_WBB_BRIDGE_WEBHOOK_ID, DISCORD_WBB_BRIDGE_WEBHOOK_TOKEN);
                $webhook->addEmbed([
                    'title' => ((strpos($postData->getTitle(), 'RE:') !== false) ? WCF::getLanguage()->get('wcf.discord.answered') : WCF::getLanguage()->get('wcf.discord.created')) . ' ' . (($postData->subject) ? $postData->subject : $thread->getTitle()),
                    'url' => $postData->getLink(),
                    'author' => [
                        'name' => $user->getUsername(),
                        'url' => $user->getLink(),
                        'icon_url' => $userProfile->getAvatar()->getURL()
                    ],
                    'description' => (strlen($message) > 200) ? substr($message, 0, 197) . '...' : $message
                ]);
                $webhook->send();
                break;
        }
    }
}