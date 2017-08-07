<?php

namespace Dirst\OkTools\Account;

use MyCLabs\Enum\Enum;

/**
 * Contains all areas on account page.
 *
 * @author Dirst <dirst.guy@gmail.com>
 * @version 1.0
 */
class OkToolsAccountAreaEnum extends Enum
{
    const NOTIFICATIONS = "Оповещения";
    const GUESTS = "Гости";
    const EVENTS = "События";
    const MAIN = "Лента";
    const FRIENDS = "Друзья";
    const MESSAGES = "Сообщения";
    const DISCUSSION = "Обсуждения";

    const SETTINGS = "Настройки";
}
