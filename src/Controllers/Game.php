<?php declare(strict_types=1);

namespace Epic\Controllers;

use Epic\Entities\GameType;
use Epic\Entities\ActionType;
use Epic\Entities\Mark;
use Epic\Repositories\GameRepository;
use Epic\Repositories\MarkRepository;
use Epic\Services\AdvancedGameService;
use Epic\Services\ClassicGameService;
use Epic\Templates\Template;

function getNextTeam($game)
{
    if (count($game->team1->marks) == 0) {
        return $game->team1->id;
    }

    if (count($game->team2->marks) == 0) {
        return $game->team2->id;
    }

    if ($game->team1->marks[0]->id > $game->team2->marks[0]->id) { // check if most recent team who played is the first one
        return $game->team2->id;
    }

    return $game->team1->id;
}

class Game
{
    public function show_advanced($game)
    {
        try {
            $gameView = new Template();
            $gameView->game = $game;

            $view = new Template();
            $view->content = $gameView->render('game-advanced.php');

            echo $view->render('layout.php');
        }
        catch (\Exception $e) {
            echo $e;
        }
    }

    public function show()
    {
        if (!isset($_GET['id'])) {
            header('Location: ' . SITE_URL);
            die();
        }

        try {
            $gameRepository = new GameRepository();

            $game = $gameRepository->getWithMarks((int)$_GET['id']);

            if ($game->type == GameType::advanced) {
                $this->show_advanced($game);
                return;
            }

            $gameView = new Template();
            $gameView->game = $game;

            $view = new Template();
            $view->content = $gameView->render('game.php');

            echo $view->render('layout.php');
        } catch (\Exception $e) {
            echo $e;
        }
    }

    public function validateClassic($post)
    {
        if (!isset($post['gameId']) && $post['gameId'] < 0) {
            return false;
        }

        if (!isset($post['x']) && $post['x'] < 0) {
            return false;
        }

        if (!isset($post['y']) && $post['y'] < 0) {
            return false;
        }

        return true;
    }

    public function validateAdvanced($post) {
        if(!isset($post['gameId']) && $post['gameId'] < 0) {
            return false;
        }

        if(!isset($post['markId']) && $post['markId'] < 0) {
            return false;
        }

        if(!isset($post['x']) && $post['x'] < 0) {
            return false;
        }

        if(!isset($post['y']) && $post['y'] < 0) {
            return false;
        }

        if (!isset($post['action']) && ($post['action'] != ActionType::placement && $post['action'] != ActionType::attack && $post['action'] != ActionType::spell)) {
            return false;
        }

        return true;
    }

    public function advanced()
    {
        header('Content-type:application/json;charset=utf-8');

        if (!$_POST && !$this->validateAdvanced($_POST)) {
            http_response_code(500);

            echo json_encode(['error' => 'server_error']);
            return;
        }

        $gameId = $_POST['gameId'];
        $markId = $_POST['markId'];
        $x = $_POST['x'];
        $y = $_POST['y'];
        $action = $_POST['action'];

        $gameRepository = new GameRepository();

        $game = $gameRepository->getWithMarks((int)$gameId);

        if (!$game) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_parameters', 'context' => 'gameId provided is not valid']);
            return;
        }

        if ($x < 0 && $x >= $game->gridWidth && $y < 0 && $y >= $game->gridHeight) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_parameters', 'context' => 'x, y coordinates not valid']);
            return;
        }

        $advancedGameService = new AdvancedGameService($game);

        if ($advancedGameService->isGameEnded()) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_action', 'context' => 'game is ended']);
            return;
        }

        $markRepository = new MarkRepository();

        if ($action == ActionType::placement) {
            if ($advancedGameService->isIllegalPlacement($markId, $x, $y)) {
                http_response_code(400);

                echo json_encode(['error' => 'invalid_action', 'context' => 'illegal placement']);
                return;
            }

            $matchingMark = null;

            foreach ($game->team1->marks as $mark) {
                if ($mark->id == $markId) {
                    $matchingMark = $mark;
                }
            }

            foreach ($game->team2->marks as $mark) {
                if ($mark->id == $markId) {
                    $matchingMark = $mark;
                }
            }

            if (!$matchingMark) {
                throw new \Exception("Something is wrong");
            }

            $matchingMark->x = $x;
            $matchingMark->y = $y;

            $markRepository->updateMarkPlacement($matchingMark);

            foreach ($game->team1->marks as &$mark) {
                if ($mark->id == $markId) {
                    $mark = $matchingMark;
                }
            }

            foreach ($game->team2->marks as &$mark) {
                if ($mark->id == $markId) {
                    $mark = $matchingMark;
                }
            }

            $response = [
                'game' => $game,
                'updatedMark' => $matchingMark
            ];

            http_response_code(200);
            echo json_encode($response);
        }

        elseif($action == ActionType::attack) {

        }
        elseif($action == ActionType::spell) {

        }

    }

    public function classic()
    {
        header('Content-type:application/json;charset=utf-8');

        if (!$_POST || !$this->validateClassic($_POST)) {
            http_response_code(500);

            echo json_encode(['error' => 'server_error']);
            return;
        }

        $gameId = $_POST['gameId'];
        $x = $_POST['x'];
        $y = $_POST['y'];

        $gameRepository = new GameRepository();

        $game = $gameRepository->getWithMarks((int)$gameId);

        if (!$game) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_parameters', 'context' => 'gameId provided is not valid']);
            return;
        }

        if ($x < 0 && $x >= $game->gridWidth && $y < 0 && $y >= $game->gridHeight) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_parameters', 'context' => 'x, y coordinates not valid']);
            return;
        }

        $classicGameService = new ClassicGameService($game);

        $teamId = getNextTeam($game);

        if ($classicGameService->isIllegalPlacement($x, $y)) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_parameters', 'context' => 'x, y coordinates illegals']);
            return;
        }

        if ($classicGameService->isGameEnded()) {
            http_response_code(400);

            echo json_encode(['error' => 'invalid_action', 'context' => 'game is ended']);
            return;
        }

        $markRepository = new MarkRepository();

        $newMark = new Mark();
        $newMark->x = $x;
        $newMark->y = $y;
        $newMark->teamId = $teamId;

        $newMark = $markRepository->insertClassic($newMark);

        $classicGameService->registerMark($newMark);

        $winner = $classicGameService->getWinner();
        $ended = $classicGameService->isGameEnded();

        if ($winner) {
            $game->winnerId = (int)$winner;
            $gameRepository->updateWinner($game);
        } elseif ($ended) {
            $game->ended = true;
            $gameRepository->updateStatus($game);
        }

        $response = [
            'game' => $game,
            'newMark' => $newMark
        ];

        http_response_code(200);
        echo json_encode($response);
    }
}
