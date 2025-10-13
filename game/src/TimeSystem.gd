extends Node

signal day_passed
signal season_changed(season)
signal hour_passed

var REAL_TIME_RATIO = 3.0  # 1 real hour = 3 game hours
var GAME_START_TIME = 0
var current_game_time = 0
var current_season = "Spring"
var seasons = ["Spring", "Summer", "Autumn", "Winter"]

func _ready():
	GAME_START_TIME = Time.get_unix_time_from_system()
	print("⏰ Time System Started - Ratio 1:" + str(REAL_TIME_RATIO))

func update_game_time(delta):
	var real_time_passed = delta
	var game_time_passed = real_time_passed * REAL_TIME_RATIO
	current_game_time += game_time_passed
	
	# Check for day change
	var previous_day = get_current_game_day()
	var current_day = int(current_game_time / (3600 * 24))
	
	if current_day > previous_day:
		day_passed.emit()
	
	# Check for season change (each season = 7 game days)
	var season_index = int(current_day / 7) % 4
	var new_season = seasons[season_index]
	if new_season != current_season:
		current_season = new_season
		season_changed.emit(current_season)
	
	# Emit hour passed signal every game hour
	if int(current_game_time / 3600) > int((current_game_time - game_time_passed) / 3600):
		hour_passed.emit()

func get_current_game_time():
	return current_game_time

func get_current_game_day():
	return int(current_game_time / (3600 * 24))

func get_current_season():
	return current_season

func get_time_of_day():
	var total_seconds = int(current_game_time) % (24 * 3600)
	var hours = int(total_seconds / 3600)
	var minutes = int((total_seconds % 3600) / 60)
	return {"hour": hours, "minute": minutes}

func get_time_string():
	var time = get_time_of_day()
	return str(time.hour).pad_zeros(2) + ":" + str(time.minute).pad_zeros(2)

func get_season_progress():
	var current_day = get_current_game_day()
	var season_day = current_day % 7
	return float(season_day) / 7.0

func accelerate_time(factor):
	REAL_TIME_RATIO = factor
	print("⏩ Time acceleration: 1:" + str(factor))