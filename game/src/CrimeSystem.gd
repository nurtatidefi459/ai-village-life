extends Node

signal crime_committed(crime_type, severity)

var crime_data = {
	"theft": {"severity": 10, "fine_silver": 50, "jail_time": 6},
	"assault": {"severity": 30, "fine_silver": 100, "jail_time": 24},
	"murder": {"severity": 100, "fine_silver": 500, "jail_time": 168}, # 7 days
	"trespassing": {"severity": 5, "fine_silver": 20, "jail_time": 2},
	"fraud": {"severity": 25, "fine_silver": 80, "jail_time": 12}
}

func commit_crime(player_data, crime_type, target_npc = ""):
	if not crime_data.has(crime_type):
		return {"success": false, "message": "Unknown crime"}
	
	var crime = crime_data[crime_type]
	player_data.crime_level += crime.severity
	
	# Notify AI system
	crime_committed.emit(crime_type, crime.severity)
	
	# Check if guards are alerted
	var alert_guards = player_data.crime_level > 20
	
	var result = {
		"success": true,
		"crime_committed": crime_type,
		"severity_added": crime.severity,
		"total_crime_level": player_data.crime_level,
		"guards_alerted": alert_guards
	}
	
	if alert_guards:
		result.message = "The guards have been alerted!"
		# Implement guard pursuit logic
	
	return result

func process_arrest(player_data):
	if player_data.crime_level < 50:
		return {"arrested": false}
	
	# Calculate punishment based on crime level
	var fine = player_data.crime_level * 10
	var jail_time_hours = player_data.crime_level * 2
	
	# Apply punishment
	player_data.silver = max(0, player_data.silver - fine)
	player_data.crime_level = max(0, player_data.crime_level - 25) # Reduce crime level after punishment
	
	return {
		"arrested": true,
		"fine_paid": fine,
		"jail_time": jail_time_hours,
		"message": "You've been arrested! Paid " + str(fine) + " silver in fines and served " + str(jail_time_hours) + " hours in jail."
	}