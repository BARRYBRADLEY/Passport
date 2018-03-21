<?php

namespace PLUGPLUS;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\Player;

class Passport extends PluginBase implements Listener, CommandExecutor{

	/** Глобальные переменные */
	public $config, $pp, $eco;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		/** Проверка на Экономику и PurePerms */
        $this->eco = $this->getServer()->getPluginManager()->getPlugin("EconomyAPI");
		$this->pp = $this->getServer()->getPluginManager()->getPlugin("PurePerms");
		
		/** Если нет нужных плагинов, то останавливаем сервер и выводим сообщение */
		if($this->eco == null || $this->pp == null){
            $this->getLogger()->critical("§cДля работы данного плагина нужен плагин §6EconomyAPI§c и §6PerePerms");
            $this->getServer()->getPluginManager()->disablePlugin($this->getServer()->getPluginManager()->getPlugin("Passport"));
            return true;
        }
		/** Подключаем конфиги */
		if(!is_dir($this->getDataFolder())){
			@mkdir($this->getDataFolder());
		}
        @mkdir($this->getDataFolder() . "players/");
		$this->saveDefaultConfig("config.yml");
		$this->config = $this->getConfig("config.yml")->getAll();
    }

	/** Заносим кол-во поставленых блоков в конфиг */
    public function onBlockPlace(BlockPlaceEvent $ev){
        $players = new Config($this->getDataFolder() . "/players/".strtolower($ev->getPlayer()->getName()).".yml", Config::YAML, array("placed" => 0, "breaked" => 0, "kills" => 0, "deaths" => 0));
        $players->set("placed", $players->get("placed") + 1);
        $players->save();
    }

	/** Заносим кол-во сломаных блоков в конфиг */
    public function onBlockBreak(BlockBreakEvent $ev){
        $players = new Config($this->getDataFolder() . "/players/".strtolower($ev->getPlayer()->getName()).".yml", Config::YAML, array("placed" => 0, "breaked" => 0, "kills" => 0, "deaths" => 0));
        $players->set("breaked", $players->get("breaked") + 1);
        $players->save();
    }

	/** Заносим кол-во смертей в конфиг */
    public function onPlayerDeath(PlayerDeathEvent $ev){
        $players = new Config($this->getDataFolder() . "/players/".strtolower($ev->getEntity()->getName()).".yml", Config::YAML, array("placed" => 0, "breaked" => 0, "kills" => 0, "deaths" => 0));
        $players->set("deaths", $players->get("deaths") + 1);
        $players->save();
    }

	/** Заносим кол-во убийств в конфиг */
    public function onEntityDamage(EntityDamageEvent $ev){
        if(!($ev instanceof EntityDamageByEntityEvent)) return;
        if(!($ev->getEntity() instanceof Player) && !($ev->getDamager() instanceof Player)) return;
        if($ev->getEntity()->getHealth() - $ev->getFinalDamage() > 0) return;
        $players = new Config($this->getDataFolder() . "/players/".strtolower($ev->getDamager()->getName()).".yml", Config::YAML, array("placed" => 0, "breaked" => 0, "kills" => 0, "deaths" => 0));
        $players->set("kills", $players->get("kills") + 1);
        $players->save();
    }
	
	/** Команда которая будет выдавать паспорт */
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool {
		$config = $this->config;
		
		/** Создаем команду */
        if($cmd->getName() == "passport" || $label == "pas" || $label == "pass" || $label == "passport"){
			
			/** Провереям игрок ли это, если нет, то выводим сообщение с конфига */
			if(!$sender instanceof Player) {
				$sender->sendMessage($config["command-no-console"]);
				return true;
			}
			
			/** Провереям на права, если нет, то выводим сообщение */
			if(!$sender->hasPermission("passport.command")){
				$sender->sendMessage($config["no-permission-command"]);
				return true;
			} 
			
			/** Выдаем предмет паспорта, ID и название берем с конфига */
			$item = Item::get($config["item"], 0, 1);
			$item->setCustomName($config["item-name"]);
			$sender->getInventory()->addItem($item);
			
			/** Выводим сообщение о выдаче паспорта */
			$sender->sendMessage($config["add-passport"]);
		}
	  return true;
    }

	/** При нажатии предметом с конфига будет выдано сообщение и забран паспорт чтоб игроки не спамили в чат */
    public function onTap(PlayerInteractEvent $event){
		$player = $event->getPlayer();
		$item = $event->getItem();
		$name = $player->getName();
		$config = $this->config;
		
		/** Проверяем на права, если нет, то выводим сообщение */
		if(!$player->hasPermission("passport.open")){
			$sender->sendMessage($config["no-permission-item"]);
			return true;
		} 
		
		/** Проверяем на ID и название предмет и выполняем далее */
		if($event->getBlock()->getId() == $config["item"] || $item->getCustomName() == $config["item-name"]){
			
			/** Выводим данные о игроке с конфига */
			$players = new Config($this->getDataFolder() . "/players/". strtolower($name).".yml", Config::YAML, array("placed" => 0, "breaked" => 0, "kills" => 0, "deaths" => 0));
			
			/** Выводим кол-во монет */
			$money = $this->eco->myMoney($player);
			if($money == false) $money = 0;
			
				/** Проверяем надо ли убирать предмет */
				if($config["item-remove"] == true){
					
					/** Убераем предмет */
					$player->getInventory()->removeItem(Item::get($config["item"], 0, 1));
					
				}
				
				/** Выводим сообщение с конфига */
				$text = $config["text"];
				
				/** Заменяем переменные на слова */
				$text = str_replace("{name}", $name, $text);
				$text = str_replace("{group}", $group = $this->pp->getUserDataMgr()->getGroup($player)->getName(), $text);
				$text = str_replace("{money}", $money, $text);
				$text = str_replace("{placed}", $players->get("placed"), $text);
				$text = str_replace("{breaked}", $players->get("breaked"), $text);
				$text = str_replace("{kills}", $players->get("kills"), $text);
				$text = str_replace("{deaths}", $players->get("deaths"), $text);
				
				/** Выводим сообщение всем */
				$this->getServer()->broadcastMessage($text);
				return true;
			}
		
	}
}