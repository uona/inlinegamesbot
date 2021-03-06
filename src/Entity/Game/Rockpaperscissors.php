<?php
/**
 * Inline Games - Telegram Bot (@inlinegamesbot)
 *
 * (c) 2016-2018 Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\inlinegamesbot\Entity\Game;

use jacklul\inlinegamesbot\Entity\Game;
use jacklul\inlinegamesbot\Helper\Utilities;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Spatie\Emoji\Emoji;

/**
 * Class Rockpaperscissors
 *
 * @package jacklul\inlinegamesbot\Entity\Game
 */
class Rockpaperscissors extends Game
{
    /**
     * Game unique ID
     *
     * @var string
     */
    protected static $code = 'rps';

    /**
     * Game name / title
     *
     * @var string
     */
    protected static $title = 'Rock-Paper-Scissors';

    /**
     * Game description
     *
     * @var string
     */
    protected static $description = 'Rock-paper-scissors is game in which each player simultaneously forms one of three shapes with an outstretched hand.';

    /**
     * Game thumbnail image
     *
     * @var string
     */
    protected static $image = 'https://i.imgur.com/1H8HI7n.png';

    /**
     * Order on the games list
     *
     * @var int
     */
    protected static $order = 20;

    /**
     * Define game symbols (emojis)
     */
    protected function defineSymbols()
    {
        $this->symbols['R'] = 'ROCK';
        $this->symbols['R_short'] = Emoji::raisedFist();
        $this->symbols['P'] = 'PAPER';
        $this->symbols['P_short'] = Emoji::raisedHand();
        $this->symbols['S'] = 'SCISSORS';
        $this->symbols['S_short'] = Emoji::victoryHand();
        $this->symbols['valid'] = ['R', 'P', 'S'];
    }

    /**
     * Game handler
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse|mixed
     *
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \jacklul\inlinegamesbot\Exception\StorageException
     */
    protected function gameAction()
    {
        if ($this->getCurrentUserId() !== $this->getUserId('host') && $this->getCurrentUserId() !== $this->getUserId('guest')) {
            return $this->answerCallbackQuery(__("You're not in this game!"), true);
        }

        $data = &$this->data['game_data'];

        $this->defineSymbols();

        $callbackquery_data = $this->manager->getUpdate()->getCallbackQuery()->getData();
        $callbackquery_data = explode(';', $callbackquery_data);

        $command = $callbackquery_data[1];

        $arg = null;
        if (isset($callbackquery_data[2])) {
            $arg = $callbackquery_data[2];
        }

        if ($command === 'start') {
            $data['host_pick'] = '';
            $data['guest_pick'] = '';
            $data['host_wins'] = 0;
            $data['guest_wins'] = 0;
            $data['round'] = 1;
            $data['current_turn'] = '';

            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Game initialization');
        } elseif ($arg === null) {
            Utilities::isDebugPrintEnabled() && Utilities::debugPrint('No move data received');
        }

        if (isset($data['current_turn']) && $data['current_turn'] == 'E') {
            return $this->answerCallbackQuery(__("This game has ended!"), true);
        }

        Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Argument: ' . $arg);

        if (isset($arg)) {
            if (in_array($arg, $this->symbols['valid'])) {
                if ($this->getCurrentUserId() === $this->getUserId('host') && $data['host_pick'] == '') {
                    $data['host_pick'] = $arg;
                } elseif ($this->getCurrentUserId() === $this->getUserId('guest') && $data['guest_pick'] == '') {
                    $data['guest_pick'] = $arg;
                }

                if ($this->saveData($this->data)) {
                    Utilities::isDebugPrintEnabled() && Utilities::debugPrint($this->getCurrentUserMention() . ' picked ' . $arg);
                } else {
                    return $this->returnStorageFailure();
                }
            } else {
                Utilities::isDebugPrintEnabled() && Utilities::debugPrint('Invalid move data: ' . $arg);

                return $this->answerCallbackQuery(__("Invalid move!"), true);
            }
        }

        $isOver = false;
        $gameOutput = '';
        $hostPick = '';
        $guestPick = '';

        if ($data['host_pick'] != '' && $data['guest_pick'] != '') {
            $isOver = $this->isGameOver($data['host_pick'], $data['guest_pick']);

            if (in_array($isOver, ['X', 'O', 'T'])) {
                $data['round'] += 1;

                if ($isOver == 'X') {
                    $data['host_wins'] = $data['host_wins'] + 1;

                    $gameOutput = '<b>' . __("{PLAYER} won this round!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>' . PHP_EOL;
                } elseif ($isOver == 'O') {
                    $data['guest_wins'] = $data['guest_wins'] + 1;

                    $gameOutput = '<b>' . __("{PLAYER} won this round!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>' . PHP_EOL;
                } else {
                    $gameOutput = '<b>' . __("This round ended with a draw!") . '</b>' . PHP_EOL;
                }
            }

            $hostPick = ' (' . $this->symbols[$data['host_pick'] . '_short'] . ')';
            $guestPick = ' (' . $this->symbols[$data['guest_pick'] . '_short'] . ')';
        }

        if (($data['host_wins'] >= 3 && $data['host_wins'] > $data['guest_wins']) || $data['host_wins'] >= $data['guest_wins'] + 3 || ($data['round'] > 5 && $data['host_wins'] > $data['guest_wins'])) {
            $gameOutput = '<b>' . __("{PLAYER} won the game!", ['{PLAYER}' => '</b>' . $this->getUserMention('host') . '<b>']) . '</b>';

            $data['current_turn'] = 'E';
        } elseif (($data['guest_wins'] >= 3 && $data['guest_wins'] > $data['host_wins']) || $data['guest_wins'] >= $data['host_wins'] + 3 || ($data['round'] > 5 && $data['guest_wins'] > $data['host_wins'])) {
            $gameOutput = '<b>' . __("{PLAYER} won the game!", ['{PLAYER}' => '</b>' . $this->getUserMention('guest') . '<b>']) . '</b>';

            $data['current_turn'] = 'E';
        } else {
            $gameOutput .= '<b>' . __("Round {ROUND} - make your picks!", ['{ROUND}' => $data['round']]) . '</b>';

            if ($data['host_pick'] != '' && $data['guest_pick'] === '') {
                $gameOutput .= PHP_EOL . '<b>' . __("Waiting for:") . '</b> ' . $this->getUserMention('guest');
            } elseif ($data['guest_pick'] != '' && $data['host_pick'] === '') {
                $gameOutput .= PHP_EOL . '<b>' . __("Waiting for:") . '</b> ' . $this->getUserMention('host');
            } else {
                $data['host_pick'] = '';
                $data['guest_pick'] = '';
            }

            $isOver = false;
        }

        if ($this->saveData($this->data)) {
            return $this->editMessage(
                $this->getUserMention('host') . (($data['host_wins'] > 0 || $data['guest_wins'] > 0) ? ' (' . $data['host_wins'] . ')' : '') . $hostPick . ' ' . __("vs.") . ' ' . $this->getUserMention('guest') . (($data['guest_wins'] > 0 || $data['host_wins'] > 0) ? ' (' . $data['guest_wins'] . ')' : '') . $guestPick . PHP_EOL . PHP_EOL . $gameOutput,
                $this->customGameKeyboard($isOver)
            );
        } else {
            return $this->returnStorageFailure();
        }
    }

    /**
     * Keyboard for game in progress
     *
     * @param bool $isOver
     *
     * @return InlineKeyboard
     * @throws \Longman\TelegramBot\Exception\TelegramException
     * @throws \jacklul\inlinegamesbot\Exception\BotException
     */
    protected function customGameKeyboard(bool $isOver = false)
    {
        if (!$isOver) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => $this->symbols['R'] . ' ' . $this->symbols['R_short'],
                        'callback_data' => self::getCode() . ';game;R',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => $this->symbols['P'] . ' ' . $this->symbols['P_short'],
                        'callback_data' => self::getCode() . ';game;P',
                    ]
                ),
                new InlineKeyboardButton(
                    [
                        'text'          => $this->symbols['S'] . ' ' . $this->symbols['S_short'],
                        'callback_data' => self::getCode() . ';game;S',
                    ]
                ),
            ];
        } else {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => __('Play again!'),
                        'callback_data' => self::getCode() . ';start',
                    ]
                ),
            ];
        }

        if (getenv('DEBUG') && $this->getCurrentUserId() == getenv('BOT_ADMIN')) {
            $inline_keyboard[] = [
                new InlineKeyboardButton(
                    [
                        'text'          => 'DEBUG: ' . 'Restart',
                        'callback_data' => self::getCode() . ';start',
                    ]
                ),
            ];
        }

        $inline_keyboard[] = [
            new InlineKeyboardButton(
                [
                    'text'          => __('Quit'),
                    'callback_data' => self::getCode() . ';quit',
                ]
            ),
            new InlineKeyboardButton(
                [
                    'text'          => __('Kick'),
                    'callback_data' => self::getCode() . ';kick',
                ]
            ),
        ];

        $inline_keyboard_markup = new InlineKeyboard(...$inline_keyboard);

        return $inline_keyboard_markup;
    }

    /**
     * Check whenever game is over
     *
     * @param string $x
     * @param string $y
     *
     * @return string
     */
    protected function isGameOver(string $x, string $y)
    {
        if ($x == 'P' && $y == 'R') {
            return 'X';
        }

        if ($y == 'P' && $x == 'R') {
            return 'O';
        }

        if ($x == 'R' && $y == 'S') {
            return 'X';
        }

        if ($y == 'R' && $x == 'S') {
            return 'O';
        }

        if ($x == 'S' && $y == 'P') {
            return 'X';
        }

        if ($y == 'S' && $x == 'P') {
            return 'O';
        }

        if ($y == $x) {
            return 'T';
        }

        return null;
    }
}
