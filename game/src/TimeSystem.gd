extends Node

var REAL_TIME_RATIO = 3.0
var GAME_START_TIME = 0
var current_game_time = 0
var current_season = "Spring"
var seasons = ["Spring", "Summer", "Autumn", "Winter"]

func _ready():
	GAME_START_TIME = Time.get_unix_time_from_system()
	print("⏰ Time System Started")

func update_game_time(delta):
	var real_time_passed = delta
	var game_time_passed = real_time_passed * REAL_TIME_RATIO
	current_game_time += game_time_passed

func get_current_game_time():
	return current_game_time

func get_current_game_day():
	return int(current_game_time / (3600 * 24))

func get_current_season():
	return current_season
