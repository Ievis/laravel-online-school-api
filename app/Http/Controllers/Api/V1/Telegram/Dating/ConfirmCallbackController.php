<?php

namespace App\Http\Controllers\Api\V1\Telegram\Dating;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConfirmCallbackController extends CommandController
{
    public function __invoke()
    {
        $decision = $this->input('decision');
        $username = $this->data->getUsername();
        $chat_id = $this->data->getChatId();
        $callback_query = $this->data->getCallbackQuery();

        if(Cache::has($username . 'user-data')) {
            die();
        }

        $register_data = new RegisterData($username);
        $fields = $register_data->get('fields');

        $this->telegram_request_service
            ->setMethodName('editMessageText')
            ->setParams([
                    'chat_id' => $chat_id,
                    'message_id' => $callback_query->message->message_id,
                    'text' => '<strong>Ваши данные:</strong>' .
                        PHP_EOL .
                        'Имя: ' . $fields['name']['value'] .
                        PHP_EOL .
                        'Предмет: ' . $fields['subject']['value'] .
                        PHP_EOL .
                        'Категория: ' . $fields['category']['value'] .
                        PHP_EOL .
                        PHP_EOL .
                        'О себе: ' . $fields['about']['value'],
                    'parse_mode' => 'html'
//                'reply_markup' => json_encode([
//                        'inline_keyboard' => [
//
//                        ]
                ]
            )
            ->make();

        if ($decision) {
            $register_service = new RegisterService();
            $register_service->setTelegramUserData($this->data);
            $register_service->setCallbackArgs($this->callback_query_args);

            $register_service->setRegisterData($register_data);
            $user = $register_service->persist();

            $user_data = $this->setUserData([
                'user' => $user
            ]);
            $relevant_user = $this->getRelevantUser();

            $message = $this->nextUserIfExists($relevant_user, true);
            if ($message) {
                $user_data->set('main_message_id', $message->result->message_id);
            }

            $user_data->save();
            return;
        }

        $chat_id = $this->data->getChatId();
        $this->telegram_request_service
            ->setMethodName('sendMessage')
            ->setParams([
                'chat_id' => $chat_id,
                'text' => '<strong>Какое из указанных ниже полей хотите изменить?</strong>',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            [
                                'text' => 'Имя',
                                'callback_data' => 'reset-name'
                            ]
                        ],
                        [
                            [
                                'text' => 'Предмет',
                                'callback_data' => 'reset-subject'
                            ]
                        ],
                        [
                            [
                                'text' => 'Категория',
                                'callback_data' => 'reset-category'
                            ]
                        ]
                    ]
                ]),
                'parse_mode' => 'html',
            ])
            ->make();

        $register_data->set('summary_message_id', $callback_query->message->message_id);
        $register_data->save();
    }
}