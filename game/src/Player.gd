extends CharacterBody2D

@export var speed: int = 100
var input_vector: Vector2 = Vector2.ZERO

func _ready():
	print("👤 Player character initialized")

func _process(delta):
	process_input()
	process_movement(delta)

func process_input():
	input_vector = Vector2.ZERO
	
	if Input.is_action_pressed("move_right"):
		input_vector.x += 1
	if Input.is_action_pressed("move_left"):
		input_vector.x -= 1
	if Input.is_action_pressed("move_down"):
		input_vector.y += 1
	if Input.is_action_pressed("move_up"):
		input_vector.y -= 1
	
	input_vector = input_vector.normalized()

func process_movement(delta):
	velocity = input_vector * speed
	move_and_slide()
	
	# Update player position in main data
	if get_parent().has_method("update_player_position"):
		get_parent().update_player_position(position)