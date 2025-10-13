extends Node

signal married(partner_name)
signal child_born(child_name)

var available_partners = []
var marriage_requirements = {}

func _ready():
	load_marriage_data()

func load_marriage_data():
	available_partners = [
		{"id": "emma", "name": "Emma", "type": "commoner", "age": 22, "requirements": {"house": true, "silver": 5000}},
		{"id": "thomas", "name": "Thomas", "type": "commoner", "age": 25, "requirements": {"house": true, "silver": 5000}},
		{"id": "lady_elena", "name": "Lady Elena", "type": "noble", "age": 28, "requirements": {"reputation": 50, "gold": 10}},
		{"id": "lord_edward", "name": "Lord Edward", "type": "noble", "age": 30, "requirements": {"reputation": 50, "gold": 10}}
	]

func can_marry(player_data, partner_id):
	var partner = get_partner(partner_id)
	if not partner:
		return false
	
	# Check requirements
	if partner.type == "commoner":
		if not player_data.house_owned:
			return false, "You need to own a house first"
		if player_data.silver < partner.requirements.silver:
			return false, "You need " + str(partner.requirements.silver) + " silver for the wedding"
	
	elif partner.type == "noble":
		if player_data.reputation < partner.requirements.reputation:
			return false, "You need " + str(partner.requirements.reputation) + " reputation"
		if player_data.gold < partner.requirements.gold:
			return false, "You need " + str(partner.requirements.gold) + " gold for the noble wedding"
	
	return true, ""

func get_partner(partner_id):
	for partner in available_partners:
		if partner.id == partner_id:
			return partner
	return null

func process_marriage(player_data, partner_id):
	var can_marry_result = can_marry(player_data, partner_id)
	if not can_marry_result[0]:
		return {"success": false, "message": can_marry_result[1]}
	
	var partner = get_partner(partner_id)
	
	# Deduct costs
	if partner.type == "commoner":
		player_data.silver -= partner.requirements.silver
	elif partner.type == "noble":
		player_data.gold -= partner.requirements.gold
	
	# Set marriage data
	player_data.married_to = partner_id
	
	# Special benefits for noble marriage
	if partner.type == "noble":
		player_data.house_owned = true
		# Unlock special quests and privileges
	
	married.emit(partner.name)
	
	return {
		"success": true, 
		"message": "Congratulations! You married " + partner.name,
		"partner": partner
	}

func check_child_birth(player_data):
	if player_data.married_to == "":
		return false
	
	# Chance for child based on marriage duration
	var marriage_duration_days = player_data.days_passed - (player_data.get("marriage_day", 0))
	if marriage_duration_days < 30: # Minimum 30 days married
		return false
	
	var birth_chance = min(0.1, marriage_duration_days * 0.001)
	if randf() < birth_chance:
		var child_name = generate_child_name()
		if not player_data.has("children"):
			player_data.children = []
		player_data.children.append(child_name)
		child_born.emit(child_name)
		return true
	
	return false

func generate_child_name():
	var names = ["Alex", "Robin", "Taylor", "Jordan", "Casey", "Jamie"]
	return names[randi() % names.size()]