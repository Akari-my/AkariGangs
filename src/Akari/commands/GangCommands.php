<?php

namespace Akari\commands;

use Akari\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;

class GangCommands extends Command{
    protected Main $plugin;

    public function __construct(Main $plugin){
        parent::__construct("gang", "Gestisci le tue gang", "§8[§eAkari§7Gangs§8]§r > /gang <create|disband|info|leave|invite|accept>", []);
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Questo comando può essere utilizzato solo in gioco.");
            return false;
        }

        if (!isset($args[0])) {
            $sender->sendMessage("======== §8[§eAkari§7Gangs§8]§r ========");
            $sender->sendMessage("§7> /gang create <name gang>");
            $sender->sendMessage("§7> /gang disband");
            $sender->sendMessage("§7> /gang info");
            $sender->sendMessage("§7> /gang leave");
            $sender->sendMessage("§7> /gang accept");
            $sender->sendMessage("§7> /gang invite <player>");
            $sender->sendMessage("======== §8[§eAkari§7Gangs§8]§r ========");
            return false;
        }

        $gangDatabase = $this->plugin->getGangDatabase();

        switch ($args[0]) {
            case "create":
                if (!isset($args[1])) {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Usa: /gang create <nome della gang>");
                    return false;
                }

                $gangDatabase->createGang($sender, $args[1]);
                break;

            case "disband":
                $gangDatabase->disbandGang($sender);
                break;

            case "info":
                $gangDatabase->gangInfo($sender);
                break;

            case "leave":
                $gangDatabase->leaveGang($sender);
                break;

            case "invite":
                $inviteeName = $args[1];
                $gangName = $gangDatabase->getPlayerGang($sender->getName());
                if ($gangName !== null) {
                   $gangDatabase->sendInvite($sender, $inviteeName, $gangName);
                } else {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Non sei in una gang, non puoi inviare inviti.");
                }
                break;

            case "accept":
                if (!isset($args[1])) {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Usa: /gang accept <nome della gang>");
                    return false;
                }

                $gangDatabase->acceptInvite($sender, $args[1]);
                break;
            case "kick":
                if (count($args) !== 2 || $args[0] !== "kick") {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Uso corretto: /gang kick <player>");
                    return true;
                }

                $playerName = $args[1];
                $gang = $this->plugin->gangDatabase->getPlayerGang($playerName);
                $senderGang = $this->plugin->gangDatabase->getPlayerGang($sender->getName());

                if ($gang === null) {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Il giocatore non fa parte di nessuna gang.");
                    return true;
                }

                if ($senderGang !== $gang) {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Il giocatore non fa parte della tua gang.");
                    return true;
                }
                
                $gangLeader = $this->plugin->gangDatabase->getGangLeader($senderGang);
                if ($sender->getName() !== $gangLeader) {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Solo il leader della gang può usare questo comando.");
                    return true;
                }
                
                if ($playerName === $gangLeader) {
                    $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Non puoi espellere te stesso da leader della gang.");
                    return true;
                }

                $this->plugin->gangDatabase->removePlayerFromGang($playerName);

                $gangMembers = $this->plugin->gangDatabase->getGangMembers($senderGang);
                foreach ($gangMembers as $member) {
                    $member->sendMessage("§8[§eAkari§7Gangs§8]§r > Il giocatore $playerName è stato espulso dalla gang.");
                }

                $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Il giocatore è stato espulso dalla gang.");

            default:
                $sender->sendMessage("§8[§eAkari§7Gangs§8]§r > Usa: /gang <create|disband|info|leave|invite|accept>");
                break;
        }

        return true;
    }
}