extends CanvasLayer

@onready var stats_label = $HUD/StatsLabel
@onready var location_label = $HUD/LocationLabel
@onready var notification_label = $HUD/NotificationLabel
@onready var game_over_screen = $GameOverScreen

func _ready():
	game_over_screen.visible = false

func update_player_stats(player_data):
	var stats_text = "Name: {name}\nAge: {age}\nSilver: {silver} | Gold: {gold}\nHealth: {health} | Hunger: {hunger} | Energy: {energy}\nReputation: {reputation} | Crime: {crime_level}".format({
		"name": player_data.character_name,
		"age": player_data.age,
		"silver": player_data.silver,
		"gold": player_data.gold,
		"health": player_data.health,
		"hunger": player_data.hunger,
		"energy": player_data.energy,
		"reputation": player_data.reputation,
		"crime_level": player_data.crime_level
	})
	
	stats_label.text = stats_text

func show_location(location_name):
	location_label.text = "Location: " + location_name

func show_event_notification(event_name):
	notification_label.text = "Event: " + event_name
	notification_label.modulate = Color.YELLOW
	
	# Create timer to clear notification
	var timer = Timer.new()
	add_child(timer)
	timer.wait_time = 3.0
	timer.one_shot = true
	timer.timeout.connect(_clear_notification)
	timer.start()

func _clear_notification():
	notification_label.text = ""

func show_game_over(message):
	game_over_screen.get_node("MessageLabel").text = message
	game_over_screen.visible = true

func _on_restart_button_pressed():
	get_tree().reload_current_scene()

func _on_main_menu_button_pressed():
	# Return to main menu
	get_tree().change_scene_to_file("res://src/MainMenu.tscn")