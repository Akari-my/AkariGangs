<?php

namespace Akari;

use Akari\commands\GangCommands;
use Akari\Database\GangDatabase;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener
{
    public GangDatabase $gangDatabase;

    public function getDatabasePath(): string
    {
        $dataFolder = $this->getDataFolder();
        $databasePath = $dataFolder . "gangs.db";
        return $databasePath;
    }

    public function onEnable(): void
    {
        $databasePath = $this->getDatabasePath();
        $this->gangDatabase = new GangDatabase($this->getServer(), $databasePath);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getCommandMap()->register("gang", new GangCommands($this));

        $this->getScheduler()->scheduleRepeatingTask(new GangTask($this), 1);
    }

    public function onEntityDamage(EntityDamageByEntityEvent $event): void {
        $damagedEntity = $event->getEntity();
        $damagerEntity = $event->getDamager();

        if (!$damagedEntity instanceof Player || !$damagerEntity instanceof Player) {
            return;
        }

        $damagedPlayerName = $damagedEntity->getName();
        $damagerPlayerName = $damagerEntity->getName();

        $damagedPlayerGang = $this->gangDatabase->getPlayerGang($damagedPlayerName);
        $damagerPlayerGang = $this->gangDatabase->getPlayerGang($damagerPlayerName);

        if ($damagedPlayerGang !== null && $damagedPlayerGang === $damagerPlayerGang) {
            $event->setCancelled(true);
        }

    }

    public function getGangDatabase(): GangDatabase
    {
        return $this->gangDatabase;
    }
}