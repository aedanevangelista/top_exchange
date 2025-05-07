<?php
session_start();
require_once '../db_connect.php';

// Set up logging for debugging
$log_file = 'chemical_recommendations_log.txt';
file_put_contents($log_file, "Chemical Recommendations Request: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'recommendations' => []
];

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    file_put_contents($log_file, "Error: Invalid request method\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Get pest types and area from the POST data
$pest_types = isset($_POST['pest_types']) ? $_POST['pest_types'] : '';
$area = isset($_POST['area']) ? floatval($_POST['area']) : 0;
$application_method = isset($_POST['application_method']) ? $_POST['application_method'] : 'spray';

// Log the received data
file_put_contents($log_file, "Received pest types: $pest_types\n", FILE_APPEND);
file_put_contents($log_file, "Received area: $area\n", FILE_APPEND);
file_put_contents($log_file, "Received application method: $application_method\n", FILE_APPEND);

// If no pest types provided, return error
if (empty($pest_types)) {
    $response['message'] = 'No pest types provided';
    file_put_contents($log_file, "Error: No pest types provided\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Parse pest types string into array
$pest_list = [];
if (strpos($pest_types, ',') !== false) {
    // Format: "Flies, Ants, Cockroaches"
    $pest_list = array_map('trim', explode(',', $pest_types));
} else if (strpos($pest_types, ' ') !== false && $pest_types !== 'Disinfect Area') {
    // Format: "Flies Ants Cockroaches" but not "Disinfect Area"
    $pest_list = array_filter(explode(' ', $pest_types), function($item) {
        return trim($item) !== '';
    });
} else {
    // Single pest type or "Disinfect Area"
    $pest_list = [trim($pest_types)];
}

// Log the parsed pest list
file_put_contents($log_file, "Parsed pest list: " . print_r($pest_list, true) . "\n", FILE_APPEND);

// Define mapping from pest types to target pests
$pest_to_target_mapping = [
    'Flies' => 'Flying Pest',
    'Mice' => 'Crawling Pest',
    'Rats' => 'Crawling Pest',
    'Mice/Rats' => 'Crawling Pest',
    'Ants' => 'Crawling Pest',
    'Mosquitoes' => 'Flying Pest',
    'Bed Bugs' => 'Crawling Pest',
    'Grass Problems' => 'Weeds',
    'Grass' => 'Weeds',
    'Cockroaches' => 'Cockroaches',
    'Termites' => 'Termites',
    'Termite' => 'Termites'
];

// Initialize target pest categories
$target_pests = [];

// Map each pest type to its target pest category
foreach ($pest_list as $pest) {
    $pest = trim($pest);

    // Check for exact matches first
    if (isset($pest_to_target_mapping[$pest])) {
        $target_pests[] = $pest_to_target_mapping[$pest];
        file_put_contents($log_file, "Mapped $pest to " . $pest_to_target_mapping[$pest] . "\n", FILE_APPEND);
        continue;
    }

    // Check for partial matches
    foreach ($pest_to_target_mapping as $key => $value) {
        if (stripos($pest, $key) !== false) {
            $target_pests[] = $value;
            file_put_contents($log_file, "Partial match: Mapped $pest to $value (matched with $key)\n", FILE_APPEND);
            break;
        }
    }
}

// Remove duplicates
$target_pests = array_unique($target_pests);

// Log the target pests
file_put_contents($log_file, "Target pests: " . implode(', ', $target_pests) . "\n", FILE_APPEND);

// If no target pests were identified, return error
if (empty($target_pests)) {
    $response['message'] = 'Could not identify target pests from the provided pest types';
    file_put_contents($log_file, "Error: No target pests identified\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Get chemicals for each target pest
$recommendations = [];

foreach ($target_pests as $target_pest) {
    // Build the query to find chemicals for this target pest
    $query = "SELECT id, chemical_name, type, target_pest, quantity, unit
              FROM chemical_inventory
              WHERE target_pest LIKE ?
              AND quantity > 0
              ORDER BY chemical_name";

    // Use wildcards to match partial target pest strings
    $search_term = "%$target_pest%";

    // Also search for "Crawling & Flying Pest" for both crawling and flying pests
    if ($target_pest == 'Crawling Pest' || $target_pest == 'Flying Pest') {
        $search_term = "%Crawling & Flying Pest%";
    }

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result = $stmt->get_result();

    $chemicals = [];
    while ($row = $result->fetch_assoc()) {
        $chemicals[] = $row;
    }

    // Log the chemicals found
    file_put_contents($log_file, "Found " . count($chemicals) . " chemicals for target pest: $target_pest\n", FILE_APPEND);

    if (!empty($chemicals)) {
        // Calculate dosage based on area and application method
        foreach ($chemicals as &$chemical) {
            // Default dosage calculation (can be adjusted based on specific requirements)
            $dosage = 0;

            // Different dosage calculations based on chemical name, target pest, and application method
            $chemical_name = strtolower($chemical['chemical_name']);
            $dosage = 0;
            $dilution_rate = 0;
            $solution_amount = 0;
            $dosage_unit = 'ml';
            $dilution_info = '';

            // Calculate the amount of diluted solution needed
            if (strpos($target_pest, 'Termites') !== false && $application_method == 'soil drench') {
                // For termite treatments (soil drench): 5 liters per 10 m²
                $solution_amount = ($area / 10) * 5; // in liters
                $dilution_info = "5 liters per 10 m²";
            } else {
                // Default for sprays: 1 liter per 100 m²
                $solution_amount = ($area / 100); // in liters
                $dilution_info = "1 liter per 100 m²";
            }

            // Determine dilution rate based on chemical name and target pest
            if (strpos($chemical_name, 'cypermethrin') !== false || strpos($chemical_name, 'permethrin') !== false) {
                // For Cypermethrin, Alpha cypermethrin, and Permethrin
                $dilution_rate = 20; // 20 ml/L
                $dosage = $dilution_rate * $solution_amount;
                file_put_contents($log_file, "Using Cypermethrin rule: $dilution_rate ml/L × $solution_amount L = $dosage ml\n", FILE_APPEND);
            }
            else if (strpos($chemical_name, 'fipronil') !== false && strpos($target_pest, 'Termites') !== false) {
                // For Fipronil targeting Termites
                $dilution_rate = 12; // 12 ml/L (1:83)
                $dosage = $dilution_rate * $solution_amount;
                file_put_contents($log_file, "Using Fipronil rule: $dilution_rate ml/L × $solution_amount L = $dosage ml\n", FILE_APPEND);
            }
            else if (strpos($chemical_name, 'imidacloprid') !== false && strpos($target_pest, 'Termites') !== false) {
                // For Imidacloprid targeting Termites
                $dilution_rate = 3.3; // 3.3 ml/L (1:300)
                $dosage = $dilution_rate * $solution_amount;
                file_put_contents($log_file, "Using Imidacloprid rule: $dilution_rate ml/L × $solution_amount L = $dosage ml\n", FILE_APPEND);
            }
            else if (strpos($chemical_name, 'emamectin benzoate') !== false || strpos($chemical_name, 'emmamectin benzoate') !== false) {
                // For Emamectin Benzoate (gel)
                // Recommend 0.5-1 gram per spot (no area-based calculation)
                $dosage = 0.75; // Average of 0.5-1 gram per spot
                $dosage_unit = 'gram per spot';
                file_put_contents($log_file, "Using Emamectin Benzoate rule: 0.5-1 gram per spot (no area calculation)\n", FILE_APPEND);
            }
            else {
                // Default calculation for other chemicals
                switch ($chemical['type']) {
                    case 'Insecticide':
                        if ($application_method == 'spray') {
                            $dilution_rate = 20; // Default 20 ml/L for insecticides
                        } else if ($application_method == 'fogging') {
                            $dilution_rate = 40; // Higher concentration for fogging
                        }
                        break;

                    case 'Herbicide':
                        $dilution_rate = 30; // 30 ml/L for herbicides
                        break;

                    case 'Rodenticide':
                        // For rodenticides: Fixed amount based on area size
                        if ($area <= 100) {
                            $dosage = 500; // grams
                            $dosage_unit = 'grams';
                            file_put_contents($log_file, "Using generic rodenticide rule: 500 grams for area <= 100 m²\n", FILE_APPEND);
                            break;
                        } else if ($area <= 500) {
                            $dosage = 1000; // grams
                            $dosage_unit = 'grams';
                            file_put_contents($log_file, "Using generic rodenticide rule: 1000 grams for area <= 500 m²\n", FILE_APPEND);
                            break;
                        } else {
                            $dosage = 2000; // grams
                            $dosage_unit = 'grams';
                            file_put_contents($log_file, "Using generic rodenticide rule: 2000 grams for area > 500 m²\n", FILE_APPEND);
                            break;
                        }

                    case 'Fungicide':
                        $dilution_rate = 25; // 25 ml/L for fungicides
                        break;

                    case 'Disinfection':
                        $dilution_rate = 50; // 50 ml/L for disinfection
                        break;

                    default:
                        $dilution_rate = 20; // Default dilution rate
                }

                // Calculate dosage based on dilution rate and solution amount
                if ($dosage == 0) { // Only calculate if not already set (for rodenticides)
                    $dosage = $dilution_rate * $solution_amount;
                    file_put_contents($log_file, "Using generic default rule: $dilution_rate ml per L × $solution_amount L = $dosage ml\n", FILE_APPEND);
                }
            }

            // Round to 2 decimal places
            $dosage = round($dosage, 2);

            // Add dosage and dilution information to chemical data
            $chemical['recommended_dosage'] = $dosage;
            $chemical['dosage_unit'] = $dosage_unit;
            $chemical['dilution_rate'] = $dilution_rate;
            $chemical['solution_amount'] = $solution_amount;
            $chemical['dilution_info'] = $dilution_info;

            // Log the calculated dosage
            file_put_contents($log_file, "Calculated dosage for " . $chemical['chemical_name'] . " (type: " . $chemical['type'] . ", target: $target_pest): $dosage $dosage_unit for area $area sqm using $application_method method\n", FILE_APPEND);
        }

        $recommendations[$target_pest] = $chemicals;
    }
}

// If no recommendations were found, return error
if (empty($recommendations)) {
    $response['message'] = 'No chemical recommendations found for the identified target pests';
    file_put_contents($log_file, "Error: No chemical recommendations found\n", FILE_APPEND);
    echo json_encode($response);
    exit;
}

// Return success response with recommendations
$response['success'] = true;
$response['message'] = 'Chemical recommendations generated successfully';
$response['recommendations'] = $recommendations;
$response['target_pests'] = $target_pests;
$response['pest_types'] = $pest_list;
$response['area'] = $area;

file_put_contents($log_file, "Success: Returning " . count($recommendations) . " recommendation categories\n", FILE_APPEND);
echo json_encode($response);
?>
