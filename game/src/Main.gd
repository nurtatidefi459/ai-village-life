extends Node2D

# Player Data
var player_data = {
	"player_id": "OFFLINE_" + str(randi() % 100000),
	"username": "Player",
	"character_name": "Adventurer",
	"age": 18,
	"health": 100,
	"hunger": 100,
	"energy": 100,
	"silver": 150,
	"gold": 0,
	"inventory": [],
	"equipment": {},
	"skills": {
		"farming": 1,
		"mining": 1,
		"combat": 1,
		"crafting": 1,
		"fishing": 1,
		"blacksmith": 1
	},
	"location": "village_square",
	"position": Vector2(0, 0),
	"game_time": 0,
	"days_passed": 0,
	"reputation": 0,
	"crime_level": 0,
	"married_to": "",
	"children": [],
	"house_owned": false,
	"last_update": 0,
	"is_online": false
}

# Systems
var ai_system
var economy_system
var time_system
var combat_system
var marriage_system
var crime_system
var http_client

# UI References
@onready var ui = $UI
@onready var player = $Player

func _ready():
	print("🎮 AI Village Life - Starting Game...")
	load_game_data()
	initialize_systems()
	setup_ui()
	check_online_status()

func initialize_systems():
	ai_system = AISystem.new()
	economy_system = EconomySystem.new()
	time_system = TimeSystem.new()
	combat_system = CombatSystem.new()
	marriage_system = MarriageSystem.new()
	crime_system = CrimeSystem.new()
	
	add_child(ai_system)
	add_child(economy_system)
	add_child(time_system)
	add_child(combat_system)
	add_child(marriage_system)
	add_child(crime_system)
	
	# Connect signals
	time_system.day_passed.connect(_on_day_passed)
	time_system.season_changed.connect(_on_season_changed)
	crime_system.crime_committed.connect(_on_crime_committed)

func setup_ui():
	ui.update_player_stats(player_data)
	ui.show_location(player_data.location)

func check_online_status():
	# Try to connect to website for online features
	http_client = HTTPRequest.new()
	add_child(http_client)
	
	var url = "http://localhost:8080/api/ping"
	var error = http_client.request(url)
	if error == OK:
		player_data.is_online = true
	else:
		player_data.is_online = false
		print("🌐 Offline mode activated")

func _process(delta):
	if time_system:
		time_system.update_game_time(delta)
	
	# Auto-save every 5 minutes game time
	if Time.get_unix_time_from_system() - player_data.last_update > 300:
		save_game_data()

func save_game_data():
	var save_dir = "user://saves/"
	var dir = DirAccess.open(save_dir)
	if not dir:
		DirAccess.make_dir_recursive_absolute(save_dir)
	
	var save_file = FileAccess.open(save_dir + "player_data.json", FileAccess.WRITE)
	if save_file:
		player_data.last_update = Time.get_unix_time_from_system()
		save_file.store_string(JSON.stringify(player_data))
		save_file.close()
		print("💾 Game saved!")

func load_game_data():
	var save_path = "user://saves/player_data.json"
	if FileAccess.file_exists(save_path):
		var save_file = FileAccess.open(save_path, FileAccess.READ)
		var data = JSON.parse_string(save_file.get_as_text())
		if data:
			player_data = data
			print("📂 Loaded saved game")
	else:
		print("🎮 New game started")
		save_game_data()

func _on_day_passed():
	player_data.days_passed += 1
	player_data.age = 18 + int(player_data.days_passed / 365)
	
	# Check for game end (30 years)
	if player_data.days_passed >= 10950: # 30 years
		end_game_aging()
	
	# Daily needs
	player_data.hunger = max(0, player_data.hunger - 20)
	player_data.energy = min(100, player_data.energy + 50)
	
	ui.update_player_stats(player_data)

func _on_season_changed(season):
	print("Season changed to: " + season)
	# AI will handle seasonal changes

func _on_crime_committed(crime_type, severity):
	print("🚨 Crime committed: " + crime_type + " Severity: " + str(severity))

func end_game_aging():
	print("🎭 Game Over - Your character has lived a full life")
	# Show ending screen
	ui.show_game_over("After 30 years, your character passes away peacefully.")

func _notification(what):
	if what == NOTIFICATION_WM_CLOSE_REQUEST:
		save_game_data()
		get_tree().quit()

# Public methods for other systems
func add_currency(silver_amount, gold_amount):
	player_data.silver += silver_amount
	player_data.gold += gold_amount
	ui.update_player_stats(player_data)

func add_item(item_id, quantity = 1):
	# Add item to inventory
	pass

func update_skill(skill, amount):
	if player_data.skills.has(skill):
		player_data.skills[skill] += amount
		ui.update_player_stats(player_data)