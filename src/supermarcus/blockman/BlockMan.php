<?php
namespace supermarcus\blockman;

use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\entity\FallingSand;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityMotionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\protocol\AddEntityPacket;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\RemoveEntityPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class BlockMan extends PluginBase implements CommandExecutor, Listener {
    private $blockMans = [];

    public function onLoad(){
        @$this->saveDefaultConfig();
    }

    public function onEnable(){
        $this->blockMans = [];
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDataPacketSend(DataPacketSendEvent $event){
        if ($event->getPacket() instanceof AddPlayerPacket){
            /** @var $pk AddPlayerPacket */
            $pk = $event->getPacket();
            if(isset($this->blockMans[spl_object_hash($event->getPlayer()->getLevel()->getEntity($pk->eid))])){
                $event->setCancelled(true);
            }
        }
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onPlayerLevelChange(EntityLevelChangeEvent $event){
        if(($event->getEntity() instanceof Player) and isset($this->blockMans[spl_object_hash($event->getEntity())])){
            /** @var $player Player */
            $player = $event->getEntity();
            $this->despawnBlockMan($player);
        }
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onPlayerDie(PlayerDeathEvent $event){
        if(isset($this->blockMans[spl_object_hash($event->getEntity())])){
            $this->despawnBlockMan($event->getEntity());
        }
    }

    /**
     * @param PlayerMoveEvent $event
     */
    public function onPlayerMove(PlayerMoveEvent $event){
        if(isset($this->blockMans[spl_object_hash($event->getPlayer())])){
            foreach ($this->blockMans[spl_object_hash($event->getPlayer())] as $p) {
                /** @var $p Player */
                $motion = $event->getPlayer()->getMotion();
                $p->addEntityMovement($event->getPlayer()->getId(), $event->getTo()->getX(), $event->getTo()->getY() + 0.5, $event->getTo()->getZ(), $event->getPlayer()->getYaw(), $event->getPlayer()->getPitch());
                $p->addEntityMotion($event->getPlayer()->getId(), $motion->getX(), $motion->getY(), $motion->getZ());
            }
        }
    }

    /**
     * @param EntityMotionEvent $event
     */
    public function onPlayerMotion(EntityMotionEvent $event){
        if(isset($this->blockMans[spl_object_hash($event->getEntity())])){
            foreach($this->blockMans[spl_object_hash($event->getEntity())] as $p){
                /** @var $p Player */
                $motion = $event->getEntity()->getMotion();
                $p->addEntityMotion($event->getEntity()->getId(), $motion->getX(), $motion->getY(), $motion->getZ());
            }
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event){
        if(isset($this->blockMans[spl_object_hash($event->getPlayer())])){
            unset($this->blockMans[spl_object_hash($event->getPlayer())]);
        }
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if(!isset($args[0]) and $sender instanceof Player){
            $target = $sender;
        }else{
            $target = @$this->getServer()->getPlayer($args[0]);
            if(!($target instanceof Player)){
                $sender->sendMessage("Invalid target");
                return false;
            }
        }
        $block = isset($args[1]) ? intval($args[1]) : Block::DIRT;
        $this->spawnBlockMan($target, $block);
        return true;
    }

    /**
     * @param Player $player
     * @param $blockId
     */
    public function spawnBlockMan(Player $player, $blockId){
        $this->despawnBlockMan($player);
        $pk = new AddEntityPacket();
        $pk->type = FallingSand::NETWORK_ID;
        $pk->eid = $player->getId();
        $pk->x = $player->getX();
        $pk->y = $player->getY();
        $pk->z = $player->getZ();
        $pk->did = -$blockId;

        foreach($player->getLevel()->getPlayers() as $p){
            if($p->getId() !== $player->getId()){
                $p->dataPacket($pk);
                $this->blockMans[spl_object_hash($player)][spl_object_hash($p)] = $p;
            }
        }
    }

    /**
     * @param Player $player
     */
    public function despawnBlockMan(Player $player){
        $player->despawnFromAll();
        $pk = new RemoveEntityPacket();
        $pk->eid = $player->getId();
        if(isset($this->blockMans[spl_object_hash($player)])){
            foreach ($this->blockMans[spl_object_hash($player)] as $p) {
                /** @var $p Player */
                $p->dataPacket($pk);
            }
            unset($this->blockMans[spl_object_hash($player)]);
        }
    }
}