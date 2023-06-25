<?php

declare(strict_types=1);

namespace Akari\Database;

use pocketmine\Player;
use pocketmine\Server;
use SQLite3;

class GangDatabase{

    private SQLite3 $db;

    private $server;

    public function __construct(Server $server, string $databasePath) {
        $this->server = $server;
        $this->db = new SQLite3($databasePath);
        $this->initDatabase();


        $this->db->exec("CREATE TABLE IF NOT EXISTS gangs (name TEXT PRIMARY KEY, owner TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS members (player TEXT PRIMARY KEY, gang TEXT);");
        $this->db->exec("CREATE TABLE IF NOT EXISTS invites (player TEXT PRIMARY KEY, gang TEXT);");
    }

    public function createGang(Player $player, string $gangName): void {
        $owner = $player->getName();

        if ($this->getPlayerGang($owner) !== null) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non puoi creare una gang perché sei già in una gang.");
            return;
        }

        $stmt = $this->db->prepare("SELECT * FROM gangs WHERE name = :name;");
        $stmt->bindValue(":name", $gangName, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Il nome della gang '{$gangName}' è già in uso. Scegli un altro nome.");
            return;
        }
        $stmt = $this->db->prepare("INSERT INTO gangs (name, owner) VALUES (:name, :owner);");
        $stmt->bindValue(":name", $gangName, SQLITE3_TEXT);
        $stmt->bindValue(":owner", $owner, SQLITE3_TEXT);
        $stmt->execute();

        $this->addPlayerToGang($player, $gangName);
        $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Hai creato con successo la gang '{$gangName}'.");
    }

    public function disbandGang(Player $player): void
    {
        $playerName = $player->getName();
        $gangName = $this->getPlayerGang($playerName);

        if ($gangName === null) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non fai parte di nessuna gang.");
            return;
        }
        $stmt = $this->db->prepare("DELETE FROM gangs WHERE name = :name AND owner = :owner;");
        $stmt->bindValue(":name", $gangName, SQLITE3_TEXT);
        $stmt->bindValue(":owner", $playerName, SQLITE3_TEXT);

        $stmt->execute();

        if ($this->db->changes() > 0) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Hai sciolto con successo la gang '{$gangName}'.");

            $stmt = $this->db->prepare("DELETE FROM members WHERE gang = :gang;");
            $stmt->bindValue(":gang", $gangName, SQLITE3_TEXT);
            $stmt->execute();
        } else {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non sei il leader della gang '{$gangName}'.");
        }
    }
    public function gangInfo(Player $player): void{
        $playerName = $player->getName();
        $gangName = $this->getPlayerGang($playerName);

        if ($gangName === null) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non fai parte di nessuna gang.");
            return;
        }
        $stmt = $this->db->prepare("SELECT owner FROM gangs WHERE name = :name;");
        $stmt->bindValue(":name", $gangName, SQLITE3_TEXT);
        $result = $stmt->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if (is_array($row)) {
            $owner = $row['owner'];
        } else {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Si è verificato un errore nel recuperare le informazioni sulla gang.");
            return;
        }

        $stmt = $this->db->prepare("SELECT player FROM members WHERE gang = :gang;");
        $stmt->bindValue(":gang", $gangName, SQLITE3_TEXT);
        $result = $stmt->execute();

        $members = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $members[] = $row['player'];
        }
        $memberList = implode(", ", $members);
        $player->sendMessage("§8[§eAkari§7Gangs Info§8]");
        $player->sendMessage(" §7> Name: §e{$gangName}");
        $player->sendMessage(" §7> Leader: §e{$owner}");
        $player->sendMessage(" §7> Membri: §e{$memberList}");
        $player->sendMessage("§8[§eAkari§7Gangs Info§8]");
    }

    public function leaveGang(Player $player): void{
        $playerName = $player->getName();
        $gangName = $this->getPlayerGang($playerName);

        if ($gangName === null) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non fai parte di nessuna gang.");
            return;
        }
        $stmt = $this->db->prepare("DELETE FROM members WHERE player = :player;");
        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->execute();

        $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Hai lasciato con successo la gang '{$gangName}'.");
    }

    public function sendInvite(Player $inviter, string $inviteeName, string $gangName): void {
        $inviterName = $inviter->getName();

        if ($inviterName === $inviteeName) {
            $inviter->sendMessage("§8[§eAkari§7Gangs§8]§r > Non puoi invitare te stesso nella tua gang.");
            return;
        }

        $invitee = $this->server->getPlayer($inviteeName);
        if ($invitee === null) {
            $inviter->sendMessage("§8[§eAkari§7Gangs§8]§r > Il giocatore '{$inviteeName}' non è online.");
            return;
        }

        if ($this->getPlayerGang($inviteeName)) {
            $inviter->sendMessage("§8[§eAkari§7Gangs§8]§r > Il giocatore '{$inviteeName}' è già in una gang.");
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO invites (player, gang, inviter) VALUES (:player, :gang, :inviter);");
        $stmt->bindValue(":player", $inviteeName, SQLITE3_TEXT);
        $stmt->bindValue(":gang", $gangName, SQLITE3_TEXT);
        $stmt->bindValue(":inviter", $inviterName, SQLITE3_TEXT);
        $stmt->execute();

        $invitee->sendMessage("§8[§eAkari§7Gangs§8]§r > {$inviter->getName()} ti ha invitato a unirti alla gang '{$gangName}'. Usa /gang accept {$gangName} per accettare l'invito.");
        $inviter->sendMessage("§8[§eAkari§7Gangs§8]§r > Hai invitato '{$inviteeName}' nella tua gang.");
    }



    public function acceptInvite(Player $player, string $gangName): void {
        $playerName = $player->getName();

        $stmt = $this->db->prepare("SELECT gang FROM invites WHERE player = :player AND gang = :gang;");
        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->bindValue(":gang", $gangName, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($this->getPlayerGang($playerName)) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non puoi accettare l'invito perché sei già in una gang.");
            return;
        }

        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $this->addPlayerToGang($player, $gangName); // Passa $player invece di $playerName

            $stmt = $this->db->prepare("DELETE FROM invites WHERE player = :player AND gang = :gang;");
            $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
            $stmt->bindValue(":gang", $gangName, SQLITE3_TEXT);
            $stmt->execute();

            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Hai accettato l'invito e sei entrato nella gang '{$gangName}'.");
        } else {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Non hai ricevuto alcun invito dalla gang '{$gangName}'.");
        }
    }

    public function getPlayerGang(string $playerName): ?string{
        $stmt = $this->db->prepare("SELECT gang FROM members WHERE player = :player;");
        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row['gang'];
        }

        return null;
    }

    public function addPlayerToGang(Player $player, string $gangName): void {
        $playerName = $player->getName();

        $existingGang = $this->getPlayerGang($playerName);
        if ($existingGang !== null) {
            $player->sendMessage("§8[§eAkari§7Gangs§8]§r > Sei già in una gang ({$existingGang}). Devi prima uscire dalla gang attuale per unirti a una nuova gang.");
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO members (player, gang) VALUES (:player, :gang);");
        $stmt->bindValue(":player", $playerName, SQLITE3_TEXT);
        $stmt->bindValue(":gang", $gangName, SQLITE3_TEXT);
        $stmt->execute();
    }

    /*public function getGangNameByPlayer(string $playerName): ?string{
        $stmt = $this->db->prepare("SELECT gangs.name FROM gangs JOIN players ON gangs.name = players.gang WHERE players.name = :name;");
        $stmt->bindValue(":name", $playerName, SQLITE3_TEXT);
        $result = $stmt->execute();

        $row = $result->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return $row['name'];
        }

        return null;
    }*/

    public function removePlayerFromGang(string $playerName): void {
        $stmt = $this->db->prepare("UPDATE players SET gang = NULL WHERE name = :name;");
        $stmt->bindValue(":name", $playerName, SQLITE3_TEXT);
        $stmt->execute();
    }

    public function initDatabase(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS gangs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        owner TEXT NOT NULL
    );");
        $this->db->exec("CREATE TABLE IF NOT EXISTS players (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        gang TEXT
    );");
        $this->db->exec("CREATE TABLE IF NOT EXISTS invites (
        id INTEGER PRIMARY KEY,
        player TEXT NOT NULL,
        gang TEXT NOT NULL
    );");

        $result = $this->db->query("PRAGMA table_info(invites);");
        $columns = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $columns[] = $row['name'];
        }

        if (!in_array('inviter', $columns)) {
            $this->db->exec("ALTER TABLE invites ADD COLUMN inviter TEXT");
        }
    }

    public function getGangLeader(string $gangName): ?string {
        // Implementa la logica per ottenere il leader della gang utilizzando il nome della gang
        // Restituisci il nome del leader della gang o null se la gang non esiste

        if (isset($this->gangs[$gangName])) {
            return $this->gangs[$gangName]["leader"];
        }

        return null;
    }

    public function getGangMembers(string $gangName): ?array {
        if (isset($this->gangs[$gangName])) {
            return $this->gangs[$gangName]["members"];
        }

        return null;
    }
}