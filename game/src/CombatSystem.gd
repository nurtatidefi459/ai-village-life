extends Node

var enemy_database = {}
var combat_log = []

func _ready():
	load_enemy_database()

func load_enemy_database():
	enemy_database = {
		"wolf": {"name": "Wolf", "health": 30, "attack": 8, "defense": 3, "xp": 10, "drops": ["wolf_pelt"]},
		"bandit": {"name": "Bandit", "health": 50, "attack": 12, "defense": 5, "xp": 20, "drops": ["silver_coin", "basic_sword"]},
		"goblin": {"name": "Goblin", "health": 25, "attack": 10, "defense": 2, "xp": 15, "drops": ["goblin_ear", "crude_dagger"]},
		"bear": {"name": "Bear", "health": 80, "attack": 15, "defense": 8, "xp": 30, "drops": ["bear_pelt", "meat"]}
	}

func calculate_damage(attacker_stats, defender_stats, attack_type = "physical"):
	var base_damage = attacker_stats.attack
	var defense = defender_stats.defense
	
	# Critical hit chance (5% base)
	var is_critical = randf() < 0.05
	if is_critical:
		base_damage *= 1.5
	
	# Calculate final damage
	var damage = max(1, base_damage - defense * 0.5)
	
	if is_critical:
		damage = int(damage)
		combat_log.append("CRITICAL HIT! " + str(damage) + " damage!")
	else:
		damage = int(damage)
		combat_log.append(str(damage) + " damage dealt")
	
	return damage

func initiate_combat(player_data, enemy_type):
	if not enemy_database.has(enemy_type):
		return {"victory": false, "error": "Unknown enemy"}
	
	var enemy = enemy_database[enemy_type].duplicate()
	var player_health = player_data.health
	var enemy_health = enemy.health
	
	var combat_round = 1
	var log = []
	
	log.append("Combat started against " + enemy.name)
	
	while player_health > 0 and enemy_health > 0:
		# Player attacks
		var player_damage = calculate_damage(
			{"attack": player_data.skills.combat * 5 + 10},
			{"defense": enemy.defense}
		)
		enemy_health -= player_damage
		log.append("Round " + str(combat_round) + ": You deal " + str(player_damage) + " damage")
		
		if enemy_health <= 0:
			break
		
		# Enemy attacks
		var enemy_damage = calculate_damage(
			{"attack": enemy.attack},
			{"defense": player_data.skills.combat * 2 + 5}
		)
		player_health -= enemy_damage
		log.append("Round " + str(combat_round) + ": " + enemy.name + " deals " + str(enemy_damage) + " damage")
		
		combat_round += 1
		if combat_round > 20: # Prevent infinite combat
			break
	
	var result = {
		"victory": player_health > 0,
		"player_health_remaining": max(0, player_health),
		"enemy_health_remaining": max(0, enemy_health),
		"rounds": combat_round,
		"log": log,
		"xp_gained": enemy.xp if player_health > 0 else 0
	}
	
	if result.victory:
		# Handle drops
		var drops = []
		for drop_item in enemy.drops:
			if get_parent().economy_system.calculate_drop("common", player_data.skills.combat * 0.1):
				drops.append(drop_item)
		result.drops = drops
	
	return result