extends Node 

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
var ui

func _ready():
	print("🎮 AI Village Life - Starting Game...")
	initialize_systems()
	setup_ui()
	check_online_status()
	load_game_data()

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
	# Create minimal UI since we don't have scene files
	ui = Node.new()
	add_child(ui)
	print("✅ Basic UI setup complete")

func check_online_status():
	player_data.is_online = false
	print("🌐 Offline mode activated")

func _process(delta):
	if time_system:
		time_system.update_game_time(delta)
	
	# Auto-save every 5 minutes real time
	if Time.get_unix_time_from_system() - player_data.last_update > 300:
		save_game_data()

func save_game_data():
	player_data.last_update = Time.get_unix_time_from_system()
	print("💾 Game saved! (Simulated)")

func load_game_data():
	print("🎮 New game started")

func _on_day_passed():
	player_data.days_passed += 1
	player_data.age = 18 + int(player_data.days_passed / 365)
	print("📅 Day passed: " + str(player_data.days_passed))

func _on_season_changed(season):
	print("Season changed to: " + season)

func _on_crime_committed(crime_type, severity):
	print("🚨 Crime committed: " + crime_type + " Severity: " + str(severity))

# Public methods for other systems
func add_currency(silver_amount, gold_amount):
	player_data.silver += silver_amount
	player_data.gold += gold_amount
	print("💰 Currency added: " + str(silver_amount) + " silver, " + str(gold_amount) + " gold")
