<?php

namespace App\Http\Controllers\Api\V1\Telegram;

use App\Services\TelegramRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use stdClass;

abstract class AbstractTelegramController
{
    protected array $commands;
    protected array $callback_queries;
    private QueryParser $query_parser;
    protected TelegramRequestService $telegram_request_service;
    protected array $callback_query_args = [];

    protected TelegramUserData $data;
    private stdClass $update_data;

    abstract public function setCommands();

    abstract public function setCallbackQueries();

    public function setTelegramUserData(?TelegramUserData $data = null)
    {
        $this->data = $data ?? new TelegramUserData(
            $this->getChatId(),
            $this->getUsername(),
            $this->getMessageIfExists(),
            $this->getCallbackQueryIfExists()
        );
    }

    public function setCallbackArgs(?array $args)
    {
        $this->callback_query_args = $args ?? [];
    }

    public function __construct()
    {
        $this->setCommands();
        $this->setCallbackQueries();

        $this->telegram_request_service = new TelegramRequestService();
        $this->query_parser = new QueryParser($this->callback_queries);
    }

    public function index(Request $request)
    {
        $request_body = $request->getContent();
        $this->update_data = json_decode($request_body);

        $this->setTelegramUserData();
        $this->handle();
    }

    private function handle()
    {
        $command = $this->validateCommand();
        $command_classname = $this->commands[$command] ?? $this->callback_queries[$command];

        $command_class = new $command_classname();
        $command_class->setTelegramUserData($this->data);
        $command_class->setCallbackArgs($this->callback_query_args);

        $command_class();
    }

    private function validateCommand()
    {
        $message = $this->data->getMessage();
        $callback_query = $this->data->getCallbackQuery();

        $command = $message->text ?? $callback_query->data ?? null;

        foreach ($this->callback_queries as $callback_query => $command_class) {
            $args_start_position = strpos($callback_query, '-{');
            $callback_query_prefix = substr($callback_query, 0, $args_start_position);

            if (str_starts_with($command, $callback_query_prefix)) {
                $this->callback_query_args = $this->query_parser->setCallbackArgs($callback_query_prefix, $command);

                return $callback_query;
            }
        }

        return array_key_exists($command, $this->commands)
            ? $command
            : '/error';
    }

    private function getUsername()
    {
        $message = $this->getMessageIfExists();
        $callback_query = $this->getCallbackQueryIfExists();

        return $message->from->username ?? $callback_query->from->username;
    }

    private function getChatId()
    {
        $message = $this->getMessageIfExists();
        $callback_query = $this->getCallbackQueryIfExists();

        return $message->chat->id ?? $callback_query->message->chat->id ?? null;
    }

    private function getMessageIfExists()
    {
        return $this->update_data->message ?? null;
    }

    private function getCallbackQueryIfExists()
    {
        return $this->update_data->callback_query ?? null;
    }

}