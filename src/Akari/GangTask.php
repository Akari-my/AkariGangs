<?php

namespace Akari;

use pocketmine\scheduler\Task;

class GangTask extends Task {

    protected Main $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick){
        foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
            $playerName = $player->getName();
            $gang = $this->plugin->gangDatabase->getPlayerGang($playerName);
            $scoreTag = ($gang !== null) ? $gang : '';
            $player->setScoreTag($scoreTag);
        }
    }
}