<?php

namespace Wilderness;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\event\entity\EntityDamageEvent;

class Main extends PluginBase implements Listener{

    private array $cooldowns = [];
    private array $noFall = [];

    public function onEnable(): void{
        $this->saveDefaultConfig();
        $this->getServer()->getPluginManager()->registerEvents($this,$this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{

        if(!$sender instanceof Player){
            return true;
        }

        if($command->getName() !== "wild"){
            return false;
        }

        $cooldown = $this->getConfig()->get("cooldown");

        if(!$sender->hasPermission("wild.bypass")){
            if(isset($this->cooldowns[$sender->getName()])){
                $time = time() - $this->cooldowns[$sender->getName()];

                if($time < $cooldown){

                    $remaining = $cooldown - $time;

                    $msg = $this->getConfig()->getNested("messages.cooldown");
                    $msg = str_replace("{time}", (string)$remaining, $msg);

                    $sender->sendMessage($msg);
                    return true;
                }
            }
        }

        $worldName = $this->getConfig()->getNested("teleport.world");
        $world = $this->getServer()->getWorldManager()->getWorldByName($worldName);

        if(!$world instanceof World){
            $sender->sendMessage("§cWorld not found.");
            return true;
        }

        $minX = $this->getConfig()->getNested("teleport.min-x");
        $maxX = $this->getConfig()->getNested("teleport.max-x");
        $minZ = $this->getConfig()->getNested("teleport.min-z");
        $maxZ = $this->getConfig()->getNested("teleport.max-z");

        $sender->sendMessage($this->getConfig()->getNested("messages.teleporting"));

        // Try up to 80 random locations
        for($i = 0; $i < 80; $i++){

            $x = mt_rand($minX, $maxX);
            $z = mt_rand($minZ, $maxZ);

            $chunkX = $x >> 4;
            $chunkZ = $z >> 4;

            $world->loadChunk($chunkX, $chunkZ);

            if(!$world->isChunkLoaded($chunkX,$chunkZ)){
                continue;
            }

            try{
                $y = $world->getHighestBlockAt($x,$z);
            }catch(\Throwable $e){
                continue;
            }

            $ground = $world->getBlockAt($x,$y,$z);
            $above = $world->getBlockAt($x,$y + 1,$z);
            $above2 = $world->getBlockAt($x,$y + 2,$z);

            if(!$ground->isSolid()){
                continue;
            }

            if(!$above->isTransparent() || !$above2->isTransparent()){
                continue;
            }

            $pos = new Position($x,$y + 1,$z,$world);

            $sender->teleport($pos);

            $sender->sendMessage($this->getConfig()->getNested("messages.success"));

            $this->cooldowns[$sender->getName()] = time();
            $this->noFall[$sender->getName()] = true;

            return true;
        }

        $sender->sendMessage("§cFailed to find a safe location. Try again.");
        return true;
    }

    public function onDamage(EntityDamageEvent $event): void{

        $entity = $event->getEntity();

        if(!$entity instanceof Player){
            return;
        }

        if($event->getCause() === EntityDamageEvent::CAUSE_FALL){

            if(isset($this->noFall[$entity->getName()])){

                $event->cancel();
                unset($this->noFall[$entity->getName()]);
            }
        }
    }
}
