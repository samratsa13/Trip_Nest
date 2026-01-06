<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Database connection
require_once 'db_connection.php';

// Create upload directories if they don't exist
$upload_dirs = ['uploads/itineraries/', 'uploads/destinations/', 'uploads/hotels/', 'uploads/activities/'];
foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Server-side image validation function
function validateImageUpload($file) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    
    if (!isset($file) || $file['error'] != 0) {
        return ['valid' => false, 'message' => 'No file uploaded or upload error occurred'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return ['valid' => false, 'message' => 'Invalid file extension. Only jpg, jpeg, png, and webp are allowed'];
    }
    
    return ['valid' => true, 'extension' => $file_extension];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    

    try {
        $pdo->exec("ALTER TABLE popular_itineraries ADD COLUMN additional_images TEXT COMMENT 'JSON array of additional image paths'");
    } catch (PDOException $e) {
        // Column might already exist, continue
    }
    
    // Add Popular Itinerary
    if (isset($_POST['add_itinerary'])) {
        $title = trim($_POST['itinerary_title'] ?? '');
        $description = trim($_POST['itinerary_description'] ?? '');
        $status = $_POST['itinerary_status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($title)) {
            $errors[] = "Title is required";
        } elseif (strlen($title) < 3 || strlen($title) > 100) {
            $errors[] = "Title must be 3-100 characters";
        }
        
        if (empty($description)) {
            $errors[] = "Description is required";
        } elseif (strlen($description) < 10 || strlen($description) > 2000) {
            $errors[] = "Description must be 10-2000 characters";
        }
        
        if (empty($errors)) {
            // Handle primary image upload (for backward compatibility)
        $image_path = '';
        $image_upload_error = '';
        if (isset($_FILES['itinerary_image'])) {
            $validation_result = validateImageUpload($_FILES['itinerary_image']);
            if (!$validation_result['valid']) {
                $image_upload_error = $validation_result['message'];
            } else {
                $target_dir = "uploads/itineraries/";
                $image_name = 'itinerary_' . time() . '.' . $validation_result['extension'];
                $target_file = $target_dir . $image_name;
                
                if (move_uploaded_file($_FILES['itinerary_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }
        
        // Handle multiple images upload
        $additional_images = [];
        $target_dir = "uploads/itineraries/";
        
        if (isset($_FILES['itinerary_images']) && is_array($_FILES['itinerary_images']['name'])) {
            $file_count = count($_FILES['itinerary_images']['name']);
            
            for ($i = 0; $i < $file_count; $i++) {
                if ($_FILES['itinerary_images']['error'][$i] == 0) {
                    // Validate each file
                    $file_to_validate = [
                        'name' => $_FILES['itinerary_images']['name'][$i],
                        'error' => $_FILES['itinerary_images']['error'][$i],
                        'tmp_name' => $_FILES['itinerary_images']['tmp_name'][$i]
                    ];
                    
                    $validation_result = validateImageUpload($file_to_validate);
                    if ($validation_result['valid']) {
                        $image_name = 'itinerary_' . time() . '_' . $i . '.' . $validation_result['extension'];
                        $target_file = $target_dir . $image_name;
                        
                        if (move_uploaded_file($_FILES['itinerary_images']['tmp_name'][$i], $target_file)) {
                            $additional_images[] = $target_file;
                        }
                    } else {
                        $image_upload_error = $validation_result['message'];
                    }
                }
            }
        }
        
        // If image upload error occurred, set it
        if (!empty($image_upload_error)) {
            $itinerary_error = $image_upload_error;
        } else {
            // If no primary image but we have additional images, use first one as primary
            if (empty($image_path) && !empty($additional_images)) {
                $image_path = $additional_images[0];
                array_shift($additional_images); // Remove it from additional images
            }
            
            // Convert additional images array to JSON
            $additional_images_json = !empty($additional_images) ? json_encode($additional_images) : null;
            
            $stmt = $pdo->prepare("INSERT INTO popular_itineraries (title, description, image_path, additional_images, status) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$title, $description, $image_path, $additional_images_json, $status])) {
                $itinerary_id = $pdo->lastInsertId();
                // Handle inline day-wise itinerary creation
                if (!empty($_POST['itinerary_days_json'])) {
                    $days = json_decode($_POST['itinerary_days_json'], true);
                    if (is_array($days)) {
                        $day_stmt = $pdo->prepare("INSERT INTO itinerary_days (itinerary_id, day_number, day_title, day_description, activities, accommodation, meals) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE day_title = VALUES(day_title), day_description = VALUES(day_description), activities = VALUES(activities), accommodation = VALUES(accommodation), meals = VALUES(meals)");
                        foreach ($days as $day) {
                            $day_number = intval($day['day_number'] ?? 0);
                            $day_title = trim($day['day_title'] ?? '');
                            if ($day_number > 0 && $day_title !== '') {
                                $day_stmt->execute([
                                    $itinerary_id,
                                    $day_number,
                                    $day_title,
                                    $day['day_description'] ?? '',
                                    $day['activities'] ?? '',
                                    $day['accommodation'] ?? '',
                                    $day['meals'] ?? ''
                                ]);
                            }
                        }
                    }
                }
                $itinerary_success = "Popular itinerary added successfully!";
            } else {
                $itinerary_error = "Error adding popular itinerary!";
            }
        }
            $itinerary_error = implode(", ", $errors);
        }
    }
    
    // Add Itinerary Day
    if (isset($_POST['add_itinerary_day'])) {
        $itinerary_id = $_POST['itinerary_id'];
        $day_number = $_POST['day_number'];
        $day_title = $_POST['day_title'];
        $day_description = $_POST['day_description'] ?? '';
        $activities = $_POST['activities'] ?? '';
        $accommodation = $_POST['accommodation'] ?? '';
        $meals = $_POST['meals'] ?? '';
        
        try {
            $stmt = $pdo->prepare("INSERT INTO itinerary_days (itinerary_id, day_number, day_title, day_description, activities, accommodation, meals) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE day_title = VALUES(day_title), day_description = VALUES(day_description), activities = VALUES(activities), accommodation = VALUES(accommodation), meals = VALUES(meals)");
            if ($stmt->execute([$itinerary_id, $day_number, $day_title, $day_description, $activities, $accommodation, $meals])) {
                $itinerary_success = "Day added successfully!";
            } else {
                $itinerary_error = "Error adding day!";
            }
        } catch (PDOException $e) {
            $itinerary_error = "Error: " . $e->getMessage();
        }
    }
    
    // Delete Itinerary Day
    if (isset($_POST['delete_itinerary_day'])) {
        $day_id = $_POST['day_id'];
        $stmt = $pdo->prepare("DELETE FROM itinerary_days WHERE id = ?");
        if ($stmt->execute([$day_id])) {
            $itinerary_success = "Day deleted successfully!";
        } else {
            $itinerary_error = "Error deleting day!";
        }
    }
    // Delete Popular Itinerary
    if (isset($_POST['delete_itinerary'])) {
        $itinerary_id = $_POST['itinerary_id'];
        
        // Get image path to delete file
        $stmt = $pdo->prepare("SELECT image_path FROM popular_itineraries WHERE id = ?");
        $stmt->execute([$itinerary_id]);
        $itinerary = $stmt->fetch();
        
        if ($itinerary && !empty($itinerary['image_path']) && file_exists($itinerary['image_path'])) {
            unlink($itinerary['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM popular_itineraries WHERE id = ?");
        if ($stmt->execute([$itinerary_id])) {
            $itinerary_success = "Popular itinerary deleted successfully!";
        } else {
            $itinerary_error = "Error deleting popular itinerary!";
        }
    }
    // Update Itinerary Status
    if (isset($_POST['update_itinerary_status'])) {
        $itinerary_id = $_POST['itinerary_id'];
        $status = $_POST['itinerary_status'];
        
        $stmt = $pdo->prepare("UPDATE popular_itineraries SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $itinerary_id])) {
            $itinerary_success = "Itinerary status updated successfully!";
        } else {
            $itinerary_error = "Error updating itinerary status!";
        }
    }
    
    // Add Destination
    if (isset($_POST['add_destination'])) {
        $name = trim($_POST['destination_name'] ?? '');
        $description = trim($_POST['destination_description'] ?? '');
        $status = $_POST['destination_status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Destination name is required";
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors[] = "Destination name must be 3-100 characters";
        }
        
        if (empty($description)) {
            $errors[] = "Description is required";
        } elseif (strlen($description) < 10 || strlen($description) > 1000) {
            $errors[] = "Description must be 10-1000 characters";
        }
        
        if (empty($errors)) {
            // Handle image upload
            $image_path = '';
            if (isset($_FILES['destination_image'])) {
                $validation_result = validateImageUpload($_FILES['destination_image']);
                if (!$validation_result['valid']) {
                    $destination_error = $validation_result['message'];
                } else {
                    $target_dir = "uploads/destinations/";
                    $image_name = 'destination_' . time() . '.' . $validation_result['extension'];
                    $target_file = $target_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['destination_image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }
            
            // Only proceed with insert if no validation error
            if (empty($destination_error)) {
                $stmt = $pdo->prepare("INSERT INTO destinations (name, description, image_path, status) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $image_path, $status])) {
                    $destination_success = "Destination added successfully!";
                } else {
                    $destination_error = "Error adding destination!";
                }
            }
        } else {
            $destination_error = implode(", ", $errors);
        }
    }
    
    // Delete Destination
    if (isset($_POST['delete_destination'])) {
        $destination_id = $_POST['destination_id'];
        
        // Get image path to delete file
        $stmt = $pdo->prepare("SELECT image_path FROM destinations WHERE id = ?");
        $stmt->execute([$destination_id]);
        $destination = $stmt->fetch();
        
        if ($destination && !empty($destination['image_path']) && file_exists($destination['image_path'])) {
            unlink($destination['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM destinations WHERE id = ?");
        if ($stmt->execute([$destination_id])) {
            $destination_success = "Destination deleted successfully!";
        } else {
            $destination_error = "Error deleting destination!";
        }
    }
    
    // Update Destination Status
    if (isset($_POST['update_destination_status'])) {
        $destination_id = $_POST['destination_id'];
        $status = $_POST['destination_status'];
        
        $stmt = $pdo->prepare("UPDATE destinations SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $destination_id])) {
            $destination_success = "Destination status updated successfully!";
        } else {
            $destination_error = "Error updating destination status!";
        }
    }
    
    // Add Hotel
    if (isset($_POST['add_hotel'])) {
        $name = trim($_POST['hotel_name'] ?? '');
        $description = trim($_POST['hotel_description'] ?? '');
        $location = trim($_POST['hotel_location'] ?? '');
        $status = $_POST['hotel_status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Hotel name is required";
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors[] = "Hotel name must be 3-100 characters";
        }
        
        if (!empty($description) && strlen($description) > 1000) {
            $errors[] = "Description must be maximum 1000 characters";
        }
        
        if (empty($location)) {
            $errors[] = "Location is required";
        } elseif (strlen($location) < 3 || strlen($location) > 100) {
            $errors[] = "Location must be 3-100 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9\s,\-]{3,100}$/', $location)) {
            $errors[] = "Location can only contain letters, numbers, spaces, commas, and hyphens";
        }
        
        if (empty($errors)) {
            $image_path = '';
            if (isset($_FILES['hotel_image'])) {
                $validation_result = validateImageUpload($_FILES['hotel_image']);
                if (!$validation_result['valid']) {
                    $hotel_error = $validation_result['message'];
                } else {
                    $target_dir = "uploads/hotels/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                    $image_name = 'hotel_' . time() . '.' . $validation_result['extension'];
                    $target_file = $target_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['hotel_image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }
            
            // Only proceed with insert if no validation error
            if (empty($hotel_error)) {
                $stmt = $pdo->prepare("INSERT INTO hotels (name, description, location, image_path, status) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $location, $image_path, $status])) {
                    $hotel_id = $pdo->lastInsertId();
                    $hotel_success = "Hotel added successfully!";
                    
                    // Add rooms if provided
                    if (!empty($_POST['rooms_json'])) {
                        $rooms = json_decode($_POST['rooms_json'], true);
                        if (is_array($rooms)) {
                            $room_stmt = $pdo->prepare("INSERT INTO hotel_rooms (hotel_id, room_type, ac_type, price_npr, quantity, available) VALUES (?, ?, ?, ?, ?, ?)");
                            foreach ($rooms as $room) {
                                if (!empty($room['room_type']) && !empty($room['ac_type']) && !empty($room['price_npr'])) {
                                    $room_stmt->execute([
                                        $hotel_id,
                                        $room['room_type'],
                                        $room['ac_type'],
                                        $room['price_npr'],
                                        $room['quantity'] ?? 1,
                                        $room['available'] ?? 1
                                    ]);
                                }
                            }
                        }
                    } else {
                        $hotel_error = "Error adding hotel!";
                    }
                }
            }
        } else {
            $hotel_error = implode(", ", $errors);
        }
    }
    
    // Delete Hotel
    if (isset($_POST['delete_hotel'])) {
        $hotel_id = $_POST['hotel_id'];
        
        $stmt = $pdo->prepare("SELECT image_path FROM hotels WHERE id = ?");
        $stmt->execute([$hotel_id]);
        $hotel = $stmt->fetch();
        
        if ($hotel && !empty($hotel['image_path']) && file_exists($hotel['image_path'])) {
            unlink($hotel['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM hotels WHERE id = ?");
        if ($stmt->execute([$hotel_id])) {
            $hotel_success = "Hotel deleted successfully!";
        } else {
            $hotel_error = "Error deleting hotel!";
        }
    }
    
    // Update Hotel Status
    if (isset($_POST['update_hotel_status'])) {
        $hotel_id = $_POST['hotel_id'];
        $status = $_POST['hotel_status'];
        
        $stmt = $pdo->prepare("UPDATE hotels SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $hotel_id])) {
            $hotel_success = "Hotel status updated successfully!";
        } else {
            $hotel_error = "Error updating hotel status!";
        }
    }
    
    // Edit Hotel
    if (isset($_POST['edit_hotel'])) {
        $hotel_id = $_POST['hotel_id'];
        $name = trim($_POST['hotel_name'] ?? '');
        $description = trim($_POST['hotel_description'] ?? '');
        $location = trim($_POST['hotel_location'] ?? '');
        $status = $_POST['hotel_status'] ?? 'active';
        
        // Validation (same as add_hotel)
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Hotel name is required";
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors[] = "Hotel name must be 3-100 characters";
        }
        
        if (!empty($description) && strlen($description) > 1000) {
            $errors[] = "Description must be maximum 1000 characters";
        }
        
        if (empty($location)) {
            $errors[] = "Location is required";
        } elseif (strlen($location) < 3 || strlen($location) > 100) {
            $errors[] = "Location must be 3-100 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9\s,\-]{3,100}$/', $location)) {
            $errors[] = "Location can only contain letters, numbers, spaces, commas, and hyphens";
        }
        
        if (empty($errors)) {
            // Handle image upload (optional - keep existing if no new image)
            $image_path = null;
            if (isset($_FILES['hotel_image']) && $_FILES['hotel_image']['error'] == 0) {
                $target_dir = "uploads/hotels/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                $image_extension = pathinfo($_FILES['hotel_image']['name'], PATHINFO_EXTENSION);
                $image_name = 'hotel_' . time() . '.' . $image_extension;
                $target_file = $target_dir . $image_name;
                
                if (move_uploaded_file($_FILES['hotel_image']['tmp_name'], $target_file)) {
                    // Delete old image if exists
                    $stmt = $pdo->prepare("SELECT image_path FROM hotels WHERE id = ?");
                    $stmt->execute([$hotel_id]);
                    $old_hotel = $stmt->fetch();
                    if ($old_hotel && !empty($old_hotel['image_path']) && file_exists($old_hotel['image_path'])) {
                        unlink($old_hotel['image_path']);
                    }
                    $image_path = $target_file;
                }
            }
            
            // Build update query
            if ($image_path) {
                $stmt = $pdo->prepare("UPDATE hotels SET name = ?, description = ?, location = ?, image_path = ?, status = ? WHERE id = ?");
                $update_success = $stmt->execute([$name, $description, $location, $image_path, $status, $hotel_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE hotels SET name = ?, description = ?, location = ?, status = ? WHERE id = ?");
                $update_success = $stmt->execute([$name, $description, $location, $status, $hotel_id]);
            }
            
            if ($update_success) {
                // Update rooms - delete existing and insert new
                if (isset($_POST['edit_rooms_json'])) {
                    // Delete existing rooms
                    $delete_stmt = $pdo->prepare("DELETE FROM hotel_rooms WHERE hotel_id = ?");
                    $delete_stmt->execute([$hotel_id]);
                    
                    // Insert new rooms
                    $rooms = json_decode($_POST['edit_rooms_json'], true);
                    if (is_array($rooms) && !empty($rooms)) {
                        $room_stmt = $pdo->prepare("INSERT INTO hotel_rooms (hotel_id, room_type, ac_type, price_npr, quantity, available) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($rooms as $room) {
                            if (!empty($room['room_type']) && !empty($room['ac_type']) && !empty($room['price_npr'])) {
                                $room_stmt->execute([
                                    $hotel_id,
                                    $room['room_type'],
                                    $room['ac_type'],
                                    $room['price_npr'],
                                    $room['quantity'] ?? 1,
                                    $room['available'] ?? 1
                                ]);
                            }
                        }
                    }
                }
                $hotel_success = "Hotel and rooms updated successfully!";
            } else {
                $hotel_error = "Error updating hotel!";
            }
        } else {
            $hotel_error = implode(", ", $errors);
        }
    }
    
    // Add Activity
    if (isset($_POST['add_activity'])) {
        $name = trim($_POST['activity_name'] ?? '');
        $description = trim($_POST['activity_description'] ?? '');
        $price_npr = $_POST['activity_price_npr'] ?? 0;
        $status = $_POST['activity_status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Activity name is required";
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors[] = "Activity name must be 3-100 characters";
        }
        
        if (!empty($description) && strlen($description) > 1000) {
            $errors[] = "Description must be maximum 1000 characters";
        }
        
        if (empty($price_npr) || !is_numeric($price_npr)) {
            $errors[] = "Price is required and must be a valid number";
        } elseif ($price_npr < 1 || $price_npr > 9999999.99) {
            $errors[] = "Price must be between NPR 1 and 9,999,999.99";
        }
        
        if (empty($errors)) {
            $image_path = '';
            if (isset($_FILES['activity_image'])) {
                $validation_result = validateImageUpload($_FILES['activity_image']);
                if (!$validation_result['valid']) {
                    $activity_error = $validation_result['message'];
                } else {
                    $target_dir = "uploads/activities/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                    $image_name = 'activity_' . time() . '.' . $validation_result['extension'];
                    $target_file = $target_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['activity_image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;
                    }
                }
            }
            
            // Only proceed with insert if no validation error
            if (empty($activity_error)) {
                $stmt = $pdo->prepare("INSERT INTO activities (name, description, image_path, price_npr, status) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $description, $image_path, $price_npr, $status])) {
                    $activity_success = "Activity added successfully!";
                } else {
                    $activity_error = "Error adding activity!";
                }
            }
        } else {
            $activity_error = implode(", ", $errors);
        }
    }
    
    // Edit Activity
    if (isset($_POST['edit_activity'])) {
        $id = $_POST['activity_id'];
        $name = trim($_POST['activity_name'] ?? '');
        $description = trim($_POST['activity_description'] ?? '');
        $price_npr = $_POST['activity_price_npr'] ?? 0;
        $status = $_POST['activity_status'] ?? 'active';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Activity name is required";
        } elseif (strlen($name) < 3 || strlen($name) > 100) {
            $errors[] = "Activity name must be 3-100 characters";
        }
        
        if (!empty($description) && strlen($description) > 1000) {
            $errors[] = "Description must be maximum 1000 characters";
        }
        
        if (empty($price_npr) || !is_numeric($price_npr)) {
            $errors[] = "Price is required and must be a valid number";
        } elseif ($price_npr < 1 || $price_npr > 9999999.99) {
            $errors[] = "Price must be between NPR 1 and 9,999,999.99";
        }
        
        if (empty($errors)) {
            $image_path = null;
            $update_image = false;
            
            // Handle image upload if provided
            if (isset($_FILES['activity_image']) && $_FILES['activity_image']['error'] == 0) {
                $validation_result = validateImageUpload($_FILES['activity_image']);
                if (!$validation_result['valid']) {
                    $activity_error = $validation_result['message'];
                } else {
                    $target_dir = "uploads/activities/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
                    $image_name = 'activity_' . time() . '.' . $validation_result['extension'];
                    $target_file = $target_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['activity_image']['tmp_name'], $target_file)) {
                        $image_path = $target_file;
                        $update_image = true;
                    }
                }
            }
            
            // Only proceed with update if no image validation error
            if (empty($activity_error)) {
                if ($update_image) {
                    $stmt = $pdo->prepare("UPDATE activities SET name = ?, description = ?, image_path = ?, price_npr = ?, status = ? WHERE id = ?");
                    $result = $stmt->execute([$name, $description, $image_path, $price_npr, $status, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE activities SET name = ?, description = ?, price_npr = ?, status = ? WHERE id = ?");
                    $result = $stmt->execute([$name, $description, $price_npr, $status, $id]);
                }
                
                if ($result) {
                    $activity_success = "Activity updated successfully!";
                } else {
                    $activity_error = "Error updating activity!";
                }
            }
        } else {
            $activity_error = implode(", ", $errors);
        }
    }
    
    // Delete Activity
    if (isset($_POST['delete_activity'])) {
        $activity_id = $_POST['activity_id'];
        
        $stmt = $pdo->prepare("SELECT image_path FROM activities WHERE id = ?");
        $stmt->execute([$activity_id]);
        $activity = $stmt->fetch();
        
        if ($activity && !empty($activity['image_path']) && file_exists($activity['image_path'])) {
            unlink($activity['image_path']);
        }
        
        $stmt = $pdo->prepare("DELETE FROM activities WHERE id = ?");
        if ($stmt->execute([$activity_id])) {
            $activity_success = "Activity deleted successfully!";
        } else {
            $activity_error = "Error deleting activity!";
        }
    }
    
    // Update Activity Status
    if (isset($_POST['update_activity_status'])) {
        $activity_id = $_POST['activity_id'];
        $status = $_POST['activity_status'];
        
        $stmt = $pdo->prepare("UPDATE activities SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $activity_id])) {
            $activity_success = "Activity status updated successfully!";
        } else {
            $activity_error = "Error updating activity status!";
        }
    }
    
    // Update Booking Status (Hotel or Activity)
    if (isset($_POST['update_booking_status'])) {
        $booking_id = $_POST['booking_id'];
        $booking_type = $_POST['booking_type'];
        $status = $_POST['booking_status'];
        
        $table = $booking_type === 'hotel' ? 'hotel_bookings' : 'activity_bookings';
        $stmt = $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $booking_id])) {
            $booking_success = "Booking status updated successfully!";
        } else {
            $booking_error = "Error updating booking status!";
        }
    }
    
    // Add User
    if (isset($_POST['add_user'])) {
        $name = trim($_POST['user_name'] ?? '');
        $email = trim($_POST['user_email'] ?? '');
        $phone = trim($_POST['user_phone'] ?? '');
        $password = $_POST['user_password'] ?? '';
        $role = $_POST['user_role'] ?? 'user';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Name is required";
        } elseif (preg_match('/^\s/', $name)) {
            $errors[] = "Name cannot start with a space";
        } elseif (preg_match('/\s{3,}/', $name)) {
            $errors[] = "Name cannot have more than two consecutive spaces";
        } elseif (preg_match('/[0-9]/', $name)) {
            $errors[] = "Name cannot contain numbers";
        } elseif (preg_match('/[^\w\s]/', $name)) {
            $errors[] = "Name cannot contain special characters";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!preg_match('/^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$/', $email)) {
            $errors[] = "Email must start with a letter, have one dot max before @, and letters only domain";
        }
        
        if (!empty($phone)) {
            $phone_clean = preg_replace('/\D/', '', $phone);
            if (!preg_match('/^(97|98)[0-9]{8}$/', $phone_clean)) {
                $errors[] = "Phone must start with 97 or 98 and be exactly 10 digits";
            }
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        } elseif (!preg_match('/[^\w]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        } elseif (preg_match('/\s/', $password)) {
            $errors[] = "Password cannot contain spaces";
        }
        
        if (empty($errors)) {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $user_error = "Email already exists!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $email, $phone, $hashed_password, $role])) {
                    $user_success = "User added successfully!";
                } else {
                    $user_error = "Error adding user!";
                }
            }
        }
    }
    
    // Delete User
    if (isset($_POST['delete_user'])) {
        $user_id = $_POST['user_id'];
        
        // Prevent admin from deleting themselves
        if ($user_id == $_SESSION['user_id']) {
            $user_error = "You cannot delete your own account!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            if ($stmt->execute([$user_id])) {
                $user_success = "User deleted successfully!";
            } else {
                $user_error = "Error deleting user!";
            }
        }
    }
}

// Get counts for dashboard
$user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$itinerary_count = $pdo->query("SELECT COUNT(*) FROM popular_itineraries")->fetchColumn();
$destination_count = $pdo->query("SELECT COUNT(*) FROM destinations")->fetchColumn();

// Get hotels, activities, and bookings counts
try {
    $hotel_count = $pdo->query("SELECT COUNT(*) FROM hotels")->fetchColumn();
} catch (PDOException $e) {
    $hotel_count = 0;
}

try {
    $activity_count = $pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
} catch (PDOException $e) {
    $activity_count = 0;
}

try {
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM hotel_bookings")->fetchColumn() + 
                      $pdo->query("SELECT COUNT(*) FROM activity_bookings")->fetchColumn();
} catch (PDOException $e) {
    $total_bookings = 0;
}

// Get comprehensive reporting data
try {
    // Booking statistics
    $hotel_bookings_count = $pdo->query("SELECT COUNT(*) FROM hotel_bookings")->fetchColumn();
    $activity_bookings_count = $pdo->query("SELECT COUNT(*) FROM activity_bookings")->fetchColumn();
    
    // Booking status breakdown
    $booking_status_stats = [
        'pending' => $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE status = 'pending'")->fetchColumn() + 
                     $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE status = 'pending'")->fetchColumn(),
        'approved' => $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE status = 'approved'")->fetchColumn() + 
                      $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE status = 'approved'")->fetchColumn(),
        'rejected' => $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE status = 'rejected'")->fetchColumn() + 
                      $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE status = 'rejected'")->fetchColumn(),
    ];
    
    // Revenue statistics
    $total_revenue_hotel = $pdo->query("SELECT COALESCE(SUM(total_price_npr), 0) FROM hotel_bookings WHERE status = 'approved'")->fetchColumn();
    $total_revenue_activity = $pdo->query("SELECT COALESCE(SUM(total_price_npr), 0) FROM activity_bookings WHERE status = 'approved'")->fetchColumn();
    $total_revenue_orders = 0;
    $total_revenue = $total_revenue_hotel + $total_revenue_activity;
    
    // Monthly bookings trend (last 6 months)
    $monthly_bookings = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_hotel_count = $pdo->query("SELECT COUNT(*) FROM hotel_bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $month_activity_count = $pdo->query("SELECT COUNT(*) FROM activity_bookings WHERE DATE_FORMAT(created_at, '%Y-%m') = '$month'")->fetchColumn();
        $monthly_bookings[] = [
            'month' => date('M Y', strtotime("-$i months")),
            'hotel' => $month_hotel_count,
            'activity' => $month_activity_count,
            'total' => $month_hotel_count + $month_activity_count
        ];
    }
    
    // Monthly orders trend removed
    $monthly_orders = [];
    
} catch (PDOException $e) {
    $hotel_bookings_count = 0;
    $activity_bookings_count = 0;
    $booking_status_stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    $total_revenue = 0;
    $total_revenue_orders = 0;
    $total_revenue_hotel = 0;
    $total_revenue_activity = 0;
    $monthly_bookings = [];
    $monthly_orders = [];
    $all_orders = [];
}

// Get data for tables
$itineraries = $pdo->query("SELECT * FROM popular_itineraries ORDER BY created_at DESC")->fetchAll();
$destinations = $pdo->query("SELECT * FROM destinations ORDER BY created_at DESC")->fetchAll();
$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recent_orders = [];

// Get all users
$all_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
$all_orders = [];

// Get hotels, activities, and bookings
try {
    $hotels = $pdo->query("SELECT * FROM hotels ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $hotels = [];
}

try {
    $activities = $pdo->query("SELECT * FROM activities ORDER BY created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $activities = [];
}

try {
    $hotel_bookings = $pdo->query("SELECT hb.*, u.name as user_name, h.name as hotel_name, hr.room_type, hr.ac_type 
        FROM hotel_bookings hb 
        JOIN users u ON hb.user_id = u.user_id 
        JOIN hotels h ON hb.hotel_id = h.id 
        JOIN hotel_rooms hr ON hb.room_id = hr.id 
        ORDER BY hb.created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $hotel_bookings = [];
}

try {
    $activity_bookings = $pdo->query("SELECT ab.*, u.name as user_name, a.name as activity_name 
        FROM activity_bookings ab 
        JOIN users u ON ab.user_id = u.user_id 
        JOIN activities a ON ab.activity_id = a.id 
        ORDER BY ab.created_at DESC")->fetchAll();
} catch (PDOException $e) {
    $activity_bookings = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Trip Nest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #031881;
            --secondary: #6f7ecb;
            --light: #f5f7fa;
            --dark: #333;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f0f2f5;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            height: 100vh;
            position: fixed;
            transition: all 0.3s;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid white;
        }
        
        .sidebar-menu i {
            margin-right: 0.8rem;
            font-size: 1.2rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .users .card-icon { background: rgba(40, 167, 69, 0.2); color: var(--success); }
        .orders .card-icon { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        /* Special offers card removed */
        .itineraries .card-icon { background: rgba(220, 53, 69, 0.2); color: var(--danger); }
        .destinations .card-icon { background: rgba(23, 162, 184, 0.2); color: #17a2b8; }
        .hotels .card-icon { background: rgba(111, 126, 203, 0.2); color: var(--secondary); }
        .activities .card-icon { background: rgba(255, 87, 34, 0.2); color: #ff5722; }
        .bookings .card-icon { background: rgba(156, 39, 176, 0.2); color: #9c27b0; }
        
        .card h3 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .status {
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d1ecf1; color: #0c5460; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 0.3rem;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: black; }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
        }
        
        .modal[style*="display: block"],
        .modal[style*="display: flex"] {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 600px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .close {
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 0.3rem;
            font-size: 1rem;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            margin-bottom: 1rem;
        }
        
        .tab {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom: 3px solid var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2, .sidebar-menu span {
                display: none;
            }
            
            .sidebar-menu a {
                justify-content: center;
                padding: 1rem;
            }
            
            .sidebar-menu i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
        }
        .alert {
            padding: 0.8rem 1.5rem;
            border-radius: 0.3rem;
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 0.5rem;
            border-radius: 0.3rem;
            display: none;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active { background: #d4edda; color: #155724; }
        .status-inactive { background: #f8d7da; color: #721c24; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.3rem 0.7rem;
            font-size: 0.8rem;
        }
        
        .text-muted {
            color: #6c757d;
            font-style: italic;
        }
        
        .field-error {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(3, 24, 129, 0.25);
        }
        
        .form-control.error {
            border-color: #dc3545;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Trip Nest Admin</h2>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>
        <?php include("components/admin-sidebar.php") ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <div class="user-info">
                <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name']); ?>&background=031881&color=fff" alt="Admin">
            </div>
        </div>

        <!-- Dashboard Tab -->
        <div id="dashboard" class="tab-content active">
            <?php include("components/admin-states.php") ?>
            
            <!-- Comprehensive Reports Section -->
            <div style="margin-top: 2rem;">
                <h2 style="margin-bottom: 1.5rem; color: var(--primary);">📊 Comprehensive Reports</h2>
                
                <!-- Revenue Overview -->
                <div class="table-container" style="margin-bottom: 2rem;">
                    <div class="table-header">
                        <h3>Revenue Overview</h3>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div style="text-align: center; padding: 1.5rem; background: linear-gradient(135deg, #031881, #6f7ecb); color: white; border-radius: 0.5rem;">
                            <h4 style="margin: 0; font-size: 0.9rem; opacity: 0.9;">Total Revenue</h4>
                            <h2 style="margin: 0.5rem 0; font-size: 2rem;">NPR <?php echo number_format($total_revenue, 2); ?></h2>
                        </div>

                        <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 0.5rem;">
                            <h4 style="margin: 0; color: #666;">Hotel Bookings</h4>
                            <h3 style="margin: 0.5rem 0; color: var(--secondary);">NPR <?php echo number_format($total_revenue_hotel, 2); ?></h3>
                        </div>
                        <div style="text-align: center; padding: 1.5rem; background: #f8f9fa; border-radius: 0.5rem;">
                            <h4 style="margin: 0; color: #666;">Activity Bookings</h4>
                            <h3 style="margin: 0.5rem 0; color: #ff5722;">NPR <?php echo number_format($total_revenue_activity, 2); ?></h3>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Grid -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <!-- Bookings Type Comparison -->
                    <div class="table-container">
                        <div class="table-header">
                            <h3>Bookings Type Comparison</h3>
                        </div>
                        <canvas id="bookingsTypeChart" style="max-height: 300px;"></canvas>
                    </div>
                    
                    <!-- Booking Status Breakdown -->
                    <div class="table-container">
                        <div class="table-header">
                            <h3>Booking Status Breakdown</h3>
                        </div>
                        <canvas id="bookingStatusChart" style="max-height: 300px;"></canvas>
                    </div>
                </div>
                
                
                
                <!-- Revenue Breakdown -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>Revenue Breakdown by Source</h3>
                    </div>
                    <canvas id="revenueChart" style="max-height: 300px;"></canvas>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Users</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($recent_users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

           
        </div>

        <!-- Users Tab -->
        <div id="users" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>All Users</h3>
                </div>
                <div style="margin-bottom: 1rem;">
                    <button class="btn btn-primary" onclick="openModal('user')">Add User</button>
                </div>
                
                <?php if (isset($user_success)): ?>
                    <div class="alert alert-success"><?php echo $user_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($user_error)): ?>
                    <div class="alert alert-error"><?php echo $user_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this user?')">Delete</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="text-muted">Current User</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Orders Tab -->
        <div id="orders" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>All Orders</h3>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_orders as $order): ?>
                        <tr>
                            <td>#<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($order['package_name']); ?></td>
                            <td>$<?php echo number_format($order['amount'], 2); ?></td>
                            <td><span class="status status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Popular Itineraries Tab -->
        <div id="itineraries" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Popular Itineraries</h3>
                    <button class="btn btn-primary" onclick="openModal('itinerary')">Add Itinerary</button>
                </div>
                
                <?php if (isset($itinerary_success)): ?>
                    <div class="alert alert-success"><?php echo $itinerary_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($itinerary_error)): ?>
                    <div class="alert alert-error"><?php echo $itinerary_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($itineraries as $itinerary): ?>
                        <tr>
                            <td><?php echo $itinerary['id']; ?></td>
                            <td><?php echo htmlspecialchars($itinerary['title']); ?></td>
                            <td><?php echo htmlspecialchars(substr($itinerary['description'], 0, 100)) . '...'; ?></td>
                            <td>
                                <?php if (!empty($itinerary['image_path'])): ?>
                                    <img src="<?php echo $itinerary['image_path']; ?>" alt="Itinerary Image" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.3rem;">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $itinerary['status']; ?>">
                                    <?php echo ucfirst($itinerary['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openDayModal(<?php echo $itinerary['id']; ?>, '<?php echo htmlspecialchars($itinerary['title']); ?>')" style="margin-right: 5px;">
                                        <i class="fas fa-calendar"></i> Manage Days
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="itinerary_id" value="<?php echo $itinerary['id']; ?>">
                                        <select name="itinerary_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block; margin-right: 5px;">
                                            <option value="active" <?php echo $itinerary['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $itinerary['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_itinerary_status" value="1">
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="itinerary_id" value="<?php echo $itinerary['id']; ?>">
                                        <button type="submit" name="delete_itinerary" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this itinerary?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Destinations Tab -->
        <div id="destinations" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Destinations</h3>
                </div>
                <div style="margin-bottom: 1rem;">
                    <button class="btn btn-primary" onclick="openModal('destination')">Add Destination</button>
                </div>
                
                <?php if (isset($destination_success)): ?>
                    <div class="alert alert-success"><?php echo $destination_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($destination_error)): ?>
                    <div class="alert alert-error"><?php echo $destination_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($destinations as $destination): ?>
                        <tr>
                            <td><?php echo $destination['id']; ?></td>
                            <td><?php echo htmlspecialchars($destination['name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($destination['description'], 0, 100)) . '...'; ?></td>
                            <td>
                                <?php if (!empty($destination['image_path'])): ?>
                                    <img src="<?php echo $destination['image_path']; ?>" alt="Destination Image" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.3rem;">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $destination['status']; ?>">
                                    <?php echo ucfirst($destination['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="destination_id" value="<?php echo $destination['id']; ?>">
                                        <select name="destination_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block; margin-right: 5px;">
                                            <option value="active" <?php echo $destination['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $destination['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_destination_status" value="1">
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="destination_id" value="<?php echo $destination['id']; ?>">
                                        <button type="submit" name="delete_destination" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this destination?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hotels Tab -->
        <div id="hotels" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Hotels</h3>
                    <button class="btn btn-primary" onclick="openModal('hotel')">Add Hotel</button>
                </div>
                
                <?php if (isset($hotel_success)): ?>
                    <div class="alert alert-success"><?php echo $hotel_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($hotel_error)): ?>
                    <div class="alert alert-error"><?php echo $hotel_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hotels)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">No hotels added yet.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($hotels as $hotel): ?>
                        <tr>
                            <td><?php echo $hotel['id']; ?></td>
                            <td><?php echo htmlspecialchars($hotel['name']); ?></td>
                            <td><?php echo htmlspecialchars($hotel['location'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($hotel['image_path'])): ?>
                                    <img src="<?php echo $hotel['image_path']; ?>" alt="Hotel Image" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.3rem;">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $hotel['status']; ?>">
                                    <?php echo ucfirst($hotel['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="hotel_id" value="<?php echo $hotel['id']; ?>">
                                        <select name="hotel_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block; margin-right: 5px;">
                                            <option value="active" <?php echo $hotel['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $hotel['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_hotel_status" value="1">
                                    </form>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openEditHotelModal(<?php echo $hotel['id']; ?>, '<?php echo htmlspecialchars(addslashes($hotel['name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($hotel['description'] ?? ''), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($hotel['location'] ?? ''), ENT_QUOTES); ?>', '<?php echo $hotel['status']; ?>', '<?php echo $hotel['image_path'] ?? ''; ?>')" style="margin-right: 5px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="hotel_id" value="<?php echo $hotel['id']; ?>">
                                        <button type="submit" name="delete_hotel" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this hotel?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activities Tab -->
        <div id="activities" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Activities</h3>
                    <button class="btn btn-primary" onclick="openModal('activity')">Add Activity</button>
                </div>
                
                <?php if (isset($activity_success)): ?>
                    <div class="alert alert-success"><?php echo $activity_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($activity_error)): ?>
                    <div class="alert alert-error"><?php echo $activity_error; ?></div>
                <?php endif; ?>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Price (NPR)</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activities)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 2rem;">No activities added yet.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($activities as $activity): ?>
                        <tr>
                            <td><?php echo $activity['id']; ?></td>
                            <td><?php echo htmlspecialchars($activity['name']); ?></td>
                            <td><?php echo htmlspecialchars(substr($activity['description'] ?? '', 0, 100)) . '...'; ?></td>
                            <td>NPR <?php echo number_format($activity['price_npr'], 2); ?></td>
                            <td>
                                <?php if (!empty($activity['image_path'])): ?>
                                    <img src="<?php echo $activity['image_path']; ?>" alt="Activity Image" style="width: 80px; height: 60px; object-fit: cover; border-radius: 0.3rem;">
                                <?php else: ?>
                                    No Image
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $activity['status']; ?>">
                                    <?php echo ucfirst($activity['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                        <select name="activity_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block; margin-right: 5px;">
                                            <option value="active" <?php echo $activity['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $activity['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <input type="hidden" name="update_activity_status" value="1">
                                    </form>
                                    <button type="button" class="btn btn-warning btn-sm" onclick="openEditActivityModal(<?php echo $activity['id']; ?>, '<?php echo htmlspecialchars(addslashes($activity['name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($activity['description'] ?? ''), ENT_QUOTES); ?>', '<?php echo $activity['price_npr']; ?>', '<?php echo $activity['status']; ?>', '<?php echo $activity['image_path'] ?? ''; ?>')" style="margin-right: 5px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                        <button type="submit" name="delete_activity" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this activity?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bookings Tab -->
        <div id="bookings" class="tab-content">
            <div class="table-container">
                <div class="table-header">
                    <h3>Bookings</h3>
                </div>
                
                <?php if (isset($booking_success)): ?>
                    <div class="alert alert-success"><?php echo $booking_success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($booking_error)): ?>
                    <div class="alert alert-error"><?php echo $booking_error; ?></div>
                <?php endif; ?>
                
                <h4 style="margin-top: 2rem; margin-bottom: 1rem;">Hotel Bookings</h4>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Hotel</th>
                            <th>Room</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Guest Info</th>
                            <th>Total (NPR)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($hotel_bookings)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 2rem;">No hotel bookings yet.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($hotel_bookings as $booking): ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['hotel_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['room_type'] . ' (' . $booking['ac_type'] . ')'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['check_in'])); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['check_out'])); ?></td>
                            <td>
                                <small><?php echo htmlspecialchars($booking['guest_name']); ?><br>
                                <?php echo htmlspecialchars($booking['guest_email']); ?><br>
                                <?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                            </td>
                            <td>NPR <?php echo number_format($booking['total_price_npr'], 2); ?></td>
                            <td>
                                <span class="status status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($booking['status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="booking_type" value="hotel">
                                    <select name="booking_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block;">
                                        <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                    <input type="hidden" name="update_booking_status" value="1">
                                </form>
                                <?php else: ?>
                                <span class="text-muted"><?php echo ucfirst($booking['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h4 style="margin-top: 3rem; margin-bottom: 1rem;">Activity Bookings</h4>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Activity</th>
                            <th>Booking Date</th>
                            <th>Guest Info</th>
                            <th>Total (NPR)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activity_bookings)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 2rem;">No activity bookings yet.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($activity_bookings as $booking): ?>
                        <tr>
                            <td><?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['activity_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                            <td>
                                <small><?php echo htmlspecialchars($booking['guest_name']); ?><br>
                                <?php echo htmlspecialchars($booking['guest_email']); ?><br>
                                <?php echo htmlspecialchars($booking['guest_phone']); ?></small>
                            </td>
                            <td>NPR <?php echo number_format($booking['total_price_npr'], 2); ?></td>
                            <td>
                                <span class="status status-<?php echo strtolower($booking['status']); ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($booking['status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                    <input type="hidden" name="booking_type" value="activity">
                                    <select name="booking_status" onchange="this.form.submit()" class="form-control" style="width: 120px; display: inline-block;">
                                        <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                    <input type="hidden" name="update_booking_status" value="1">
                                </form>
                                <?php else: ?>
                                <span class="text-muted"><?php echo ucfirst($booking['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Itinerary Modal -->
    <div id="itineraryModal" class="modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>Add Popular Itinerary</h3>
                <span class="close" onclick="closeModal('itineraryModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="itineraryForm">
                <input type="hidden" name="add_itinerary" value="1">
                <input type="hidden" name="itinerary_days_json" id="itinerary_days_json">
                <div class="form-group">
                    <label for="itinerary_title">Title *</label>
                    <input type="text" id="itinerary_title" name="itinerary_title" class="form-control" 
                           pattern="^.{3,100}$" 
                           title="Title must be 3-100 characters"
                           required>
                    <div id="itinerary_title_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="itinerary_description">Description *</label>
                    <textarea id="itinerary_description" name="itinerary_description" class="form-control" 
                              minlength="10" 
                              maxlength="2000"
                              title="Description must be 10-2000 characters"
                              required></textarea>
                    <div id="itinerary_description_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="itinerary_image">Primary Image (Optional - First image will be used if not specified)</label>
                    <input type="file" id="itinerary_image" name="itinerary_image" class="form-control" accept="image/*" onchange="previewImage(this, 'itineraryPreview')">
                    <img id="itineraryPreview" class="image-preview" src="#" alt="Image Preview">
                </div>
                <div class="form-group">
                    <label for="itinerary_images">Additional Images (Select multiple images)</label>
                    <input type="file" id="itinerary_images" name="itinerary_images[]" class="form-control" accept="image/*" multiple onchange="previewMultipleImages(this, 'itineraryImagesPreview')">
                    <small style="color: #666;">Hold Ctrl/Cmd to select multiple images</small>
                    <div id="itineraryImagesPreview" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem; margin-top: 1rem;"></div>
                </div>
                <div class="form-group">
                    <label for="itinerary_status">Status</label>
                    <select id="itinerary_status" name="itinerary_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="border: 1px solid #e0e0e0; border-radius: 0.5rem; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <h4 style="margin: 0;">Day-wise Itinerary</h4>
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="addDayBlock()">Add Day</button>
                            <button type="button" class="btn btn-light" onclick="clearDayBlocks()">Clear Days</button>
                        </div>
                    </div>
                    <p style="color:#666; margin:0.5rem 0 1rem;">Add day details right now while creating the itinerary.</p>
                    <div id="dayBlocksContainer" style="max-height: 300px; overflow-y: auto; display: grid; gap: 1rem;"></div>
                </div>
                <button type="submit" class="btn btn-primary">Save Itinerary</button>
            </form>
        </div>
    </div>

    <!-- Manage Days Modal -->
    <div id="dayModal" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3 id="dayModalTitle">Manage Days</h3>
                <span class="close" onclick="closeModal('dayModal')">&times;</span>
            </div>
            <div id="dayModalContent">
                <div id="existingDays">
                    <!-- Existing days will be loaded here -->
                </div>
                <hr style="margin: 2rem 0;">
                <h4>Add New Day</h4>
                <form method="POST" id="addDayForm">
                    <input type="hidden" name="add_itinerary_day" value="1">
                    <input type="hidden" name="itinerary_id" id="dayItineraryId">
                    <div class="form-group">
                        <label for="day_number">Day Number *</label>
                        <input type="number" id="day_number" name="day_number" class="form-control" 
                               min="1" max="365"
                               title="Day number must be between 1 and 365"
                               required>
                        <div id="day_number_error" class="field-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="day_title">Day Title *</label>
                        <input type="text" id="day_title" name="day_title" class="form-control" 
                               pattern="^.{3,100}$" 
                               title="Day title must be 3-100 characters"
                               required>
                        <div id="day_title_error" class="field-error"></div>
                    </div>
                    <div class="form-group">
                        <label for="day_description">Day Description</label>
                        <textarea id="day_description" name="day_description" class="form-control" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="activities">Activities</label>
                        <textarea id="activities" name="activities" class="form-control" rows="3" placeholder="List activities for this day"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="accommodation">Accommodation</label>
                        <input type="text" id="accommodation" name="accommodation" class="form-control" placeholder="Hotel name or accommodation type">
                    </div>
                    <div class="form-group">
                        <label for="meals">Meals</label>
                        <input type="text" id="meals" name="meals" class="form-control" placeholder="e.g., Breakfast, Lunch, Dinner">
                    </div>
                    <button type="submit" class="btn btn-primary">Add Day</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('addDayForm').reset(); calculateNextDayNumber(document.getElementById('dayItineraryId').value);">Clear Form</button>
                </form>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <span class="close" onclick="closeModal('userModal')">&times;</span>
            </div>
            <form method="POST" id="userForm">
                <input type="hidden" name="add_user" value="1">
                <div class="form-group">
                    <label for="user_name">Full Name *</label>
                    <input type="text" id="user_name" name="user_name" class="form-control" 
                           pattern="^(?!\s)(?!.*\s{3,})(?!.*\d)(?!.*_)(?!.*[^\w\s]).+$" 
                           title="No leading spaces, no numbers/special chars, no triple spaces"
                           required>
                    <div id="user_name_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="user_email">Email Address *</label>
                    <input type="email" id="user_email" name="user_email" class="form-control" 
                           pattern="^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$" 
                           title="Start with letter, one dot max before @, letters only domain"
                           required>
                    <div id="user_email_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="user_phone">Phone Number</label>
                    <input type="tel" id="user_phone" name="user_phone" class="form-control" 
                           pattern="^(97|98)[0-9]{8}$" 
                           title="Must start with 97 or 98 and be exactly 10 digits">
                    <div id="user_phone_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="user_password">Password *</label>
                    <input type="password" id="user_password" name="user_password" class="form-control" 
                           pattern="^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[^A-Za-z0-9])\S{8,}$" 
                           title="Min 8 chars, upper, lower, number, special, no spaces"
                           required minlength="8">
                    <div id="user_password_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="user_role">Role *</label>
                    <select id="user_role" name="user_role" class="form-control" required>
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </div>
    </div>

    <!-- Hotel Modal -->
    <div id="hotelModal" class="modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>Add Hotel</h3>
                <span class="close" onclick="closeModal('hotelModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="hotelForm">
                <input type="hidden" name="add_hotel" value="1">
                <input type="hidden" name="rooms_json" id="hotel_rooms_json">
                <div class="form-group">
                    <label for="hotel_name">Hotel Name *</label>
                    <input type="text" id="hotel_name" name="hotel_name" class="form-control" 
                           pattern="^.{3,100}$" 
                           title="Hotel name must be 3-100 characters"
                           required>
                    <div id="hotel_name_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="hotel_description">Description</label>
                    <textarea id="hotel_description" name="hotel_description" class="form-control" rows="4" 
                              maxlength="1000"
                              title="Description must be maximum 1000 characters"></textarea>
                    <div id="hotel_description_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="hotel_location">Location *</label>
                    <input type="text" id="hotel_location" name="hotel_location" class="form-control" 
                           pattern="^[a-zA-Z0-9\s,\-]{3,100}$" 
                           title="Location must be 3-100 characters (letters, numbers, spaces, commas, hyphens)"
                           required>
                    <div id="hotel_location_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="hotel_image">Hotel Image</label>
                    <input type="file" id="hotel_image" name="hotel_image" class="form-control" accept="image/*" onchange="previewImage(this, 'hotelPreview')">
                    <img id="hotelPreview" class="image-preview" src="#" alt="Image Preview">
                </div>
                <div class="form-group">
                    <label for="hotel_status">Status</label>
                    <select id="hotel_status" name="hotel_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="border: 1px solid #e0e0e0; border-radius: 0.5rem; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <h4 style="margin: 0;">Hotel Rooms</h4>
                        <button type="button" class="btn btn-secondary" onclick="addRoomBlock()">Add Room</button>
                    </div>
                    <p style="color:#666; margin:0.5rem 0 1rem;">Add room types with AC/Non-AC options and prices in NPR.</p>
                    <div id="roomBlocksContainer" style="max-height: 300px; overflow-y: auto; display: grid; gap: 1rem;"></div>
                </div>
                <button type="submit" class="btn btn-primary">Save Hotel</button>
            </form>
        </div>
    </div>

    <!-- Edit Hotel Modal -->
    <div id="editHotelModal" class="modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>Edit Hotel</h3>
                <span class="close" onclick="closeModal('editHotelModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editHotelForm">
                <input type="hidden" name="edit_hotel" value="1">
                <input type="hidden" name="hotel_id" id="edit_hotel_id">
                <div class="form-group">
                    <label for="edit_hotel_name">Hotel Name *</label>
                    <input type="text" id="edit_hotel_name" name="hotel_name" class="form-control" 
                           pattern="^.{3,100}$" 
                           title="Hotel name must be 3-100 characters"
                           required>
                    <div id="edit_hotel_name_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="edit_hotel_description">Description</label>
                    <textarea id="edit_hotel_description" name="hotel_description" class="form-control" rows="4" 
                              maxlength="1000"
                              title="Description must be maximum 1000 characters"></textarea>
                    <div id="edit_hotel_description_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="edit_hotel_location">Location *</label>
                    <input type="text" id="edit_hotel_location" name="hotel_location" class="form-control" 
                           pattern="^[a-zA-Z0-9\s,\-]{3,100}$" 
                           title="Location must be 3-100 characters (letters, numbers, spaces, commas, hyphens)"
                           required>
                    <div id="edit_hotel_location_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="edit_hotel_image">Hotel Image (Leave empty to keep current image)</label>
                    <div id="current_hotel_image_container" style="margin-bottom: 10px;">
                        <img id="current_hotel_image" src="" alt="Current Image" style="max-width: 200px; max-height: 150px; border-radius: 0.3rem; display: none;">
                        <p id="no_current_image" style="color: #666; font-style: italic; display: none;">No current image</p>
                    </div>
                    <input type="file" id="edit_hotel_image" name="hotel_image" class="form-control" accept="image/*" onchange="previewImage(this, 'editHotelPreview')">
                    <img id="editHotelPreview" class="image-preview" src="#" alt="New Image Preview">
                </div>
                <div class="form-group">
                    <label for="edit_hotel_status">Status</label>
                    <select id="edit_hotel_status" name="hotel_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="border: 1px solid #e0e0e0; border-radius: 0.5rem; padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
                        <h4 style="margin: 0;">Hotel Rooms</h4>
                        <button type="button" class="btn btn-secondary" onclick="addEditRoomBlock()">Add Room</button>
                    </div>
                    <p style="color:#666; margin:0.5rem 0 1rem;">Edit room types with AC/Non-AC options and prices in NPR.</p>
                    <div id="editRoomBlocksContainer" style="max-height: 300px; overflow-y: auto; display: grid; gap: 1rem;">
                        <p id="loadingRooms" style="color: #666; font-style: italic;">Loading rooms...</p>
                    </div>
                </div>
                <input type="hidden" name="edit_rooms_json" id="edit_rooms_json">
                <button type="submit" class="btn btn-primary">Update Hotel</button>
            </form>
        </div>
    </div>

    <!-- Destination Modal -->
    <div id="destinationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Destination</h3>
                <span class="close" onclick="closeModal('destinationModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="destinationForm">
                <input type="hidden" name="add_destination" value="1">
                <div class="form-group">
                    <label for="destination_name">Destination Name *</label>
                    <input type="text" id="destination_name" name="destination_name" class="form-control" 
                           pattern="^.{3,100}$" 
                           title="Destination name must be 3-100 characters"
                           required>
                    <div id="destination_name_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="destination_description">Description *</label>
                    <textarea id="destination_description" name="destination_description" class="form-control" rows="4" 
                              minlength="10" 
                              maxlength="1000"
                              title="Description must be 10-1000 characters"
                              required></textarea>
                    <div id="destination_description_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="destination_image">Destination Image</label>
                    <input type="file" id="destination_image" name="destination_image" class="form-control" accept="image/*" onchange="previewImage(this, 'destinationPreview')">
                    <img id="destinationPreview" class="image-preview" src="#" alt="Image Preview">
                </div>
                <div class="form-group">
                    <label for="destination_status">Status</label>
                    <select id="destination_status" name="destination_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Destination</button>
            </form>
        </div>
    </div>

    <!-- Activity Modal -->
    <div id="activityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Activity</h3>
                <span class="close" onclick="closeModal('activityModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="activityForm">
                <input type="hidden" name="add_activity" value="1">
                <div class="form-group">
                    <label for="activity_name">Activity Name *</label>
                    <input type="text" id="activity_name" name="activity_name" class="form-control" 
                           pattern="^.{3,100}$" 
                           title="Activity name must be 3-100 characters"
                           required>
                    <div id="activity_name_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="activity_description">Description</label>
                    <textarea id="activity_description" name="activity_description" class="form-control" rows="4" 
                              maxlength="1000"
                              title="Description must be maximum 1000 characters"></textarea>
                    <div id="activity_description_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="activity_price_npr">Price (NPR) *</label>
                    <input type="number" id="activity_price_npr" name="activity_price_npr" class="form-control" 
                           step="0.01" min="1" max="9999999.99"
                           title="Price must be between NPR 1 and 9,999,999.99"
                           required>
                    <div id="activity_price_npr_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="activity_image">Activity Image</label>
                    <input type="file" id="activity_image" name="activity_image" class="form-control" accept="image/*" onchange="previewImage(this, 'activityPreview')">
                    <img id="activityPreview" class="image-preview" src="#" alt="Image Preview">
                </div>
                <div class="form-group">
                    <label for="activity_status">Status</label>
                    <select id="activity_status" name="activity_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Save Activity</button>
            </form>
        </div>
    </div>

    <!-- Edit Activity Modal -->
    <div id="editActivityModal" class="modal">
        <div class="modal-content" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>Edit Activity</h3>
                <span class="close" onclick="closeModal('editActivityModal')">&times;</span>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editActivityForm">
                <input type="hidden" name="edit_activity" value="1">
                <input type="hidden" name="activity_id" id="edit_activity_id">
                <div class="form-group">
                    <label for="edit_activity_name">Activity Name *</label>
                    <input type="text" id="edit_activity_name" name="activity_name" class="form-control" 
                           pattern="^.{3,100}$" 
                           title="Activity name must be 3-100 characters"
                           required>
                    <div id="edit_activity_name_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="edit_activity_description">Description</label>
                    <textarea id="edit_activity_description" name="activity_description" class="form-control" rows="4" 
                              maxlength="1000"
                              title="Description must be maximum 1000 characters"></textarea>
                    <div id="edit_activity_description_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="edit_activity_price_npr">Price (NPR) *</label>
                    <input type="number" id="edit_activity_price_npr" name="activity_price_npr" class="form-control" 
                           step="0.01" min="1" max="9999999.99"
                           title="Price must be between NPR 1 and 9,999,999.99"
                           required>
                    <div id="edit_activity_price_npr_error" class="field-error"></div>
                </div>
                <div class="form-group">
                    <label for="edit_activity_image">Activity Image (Leave empty to keep current image)</label>
                    <div id="current_activity_image_container" style="margin-bottom: 10px;">
                        <img id="current_activity_image" src="" alt="Current Image" style="max-width: 200px; max-height: 150px; border-radius: 0.3rem; display: none;">
                        <p id="no_current_activity_image" style="color: #666; font-style: italic; display: none;">No current image</p>
                    </div>
                    <input type="file" id="edit_activity_image" name="activity_image" class="form-control" accept="image/*" onchange="previewImage(this, 'editActivityPreview')">
                    <img id="editActivityPreview" class="image-preview" src="#" alt="New Image Preview">
                </div>
                <div class="form-group">
                    <label for="edit_activity_status">Status</label>
                    <select id="edit_activity_status" name="activity_status" class="form-control" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Update Activity</button>
            </form>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.getAttribute('data-tab')) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs and links
                    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    document.getElementById(this.getAttribute('data-tab')).classList.add('active');
                }
            });
        });
        
        // Modal functionality
        function openModal(type) {
            document.getElementById(type + 'Modal').style.display = 'flex';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
        });

        // Image preview function
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.addEventListener('load', function() {
                    preview.src = reader.result;
                    preview.style.display = 'block';
                });
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Open Edit Hotel Modal and populate with data
        function openEditHotelModal(id, name, description, location, status, imagePath) {
            // Set form values
            document.getElementById('edit_hotel_id').value = id;
            document.getElementById('edit_hotel_name').value = name;
            document.getElementById('edit_hotel_description').value = description;
            document.getElementById('edit_hotel_location').value = location;
            document.getElementById('edit_hotel_status').value = status;
            
            // Show current image
            const currentImageEl = document.getElementById('current_hotel_image');
            const noImageEl = document.getElementById('no_current_image');
            if (imagePath && imagePath !== '') {
                currentImageEl.src = imagePath;
                currentImageEl.style.display = 'block';
                noImageEl.style.display = 'none';
            } else {
                currentImageEl.style.display = 'none';
                noImageEl.style.display = 'block';
            }
            
            // Reset new image preview
            document.getElementById('editHotelPreview').style.display = 'none';
            document.getElementById('edit_hotel_image').value = '';
            
            // Load rooms for this hotel
            loadHotelRooms(id);
            
            // Open modal
            document.getElementById('editHotelModal').style.display = 'flex';
        }
        
        // Load hotel rooms for editing
        let editRoomBlockCounter = 0;
        
        function loadHotelRooms(hotelId) {
            const container = document.getElementById('editRoomBlocksContainer');
            container.innerHTML = '<p style="color: #666; font-style: italic;">Loading rooms...</p>';
            editRoomBlockCounter = 0;
            
            fetch('get_hotel_rooms.php?hotel_id=' + hotelId)
                .then(response => response.json())
                .then(data => {
                    container.innerHTML = '';
                    if (data.success && data.rooms.length > 0) {
                        data.rooms.forEach(room => {
                            addEditRoomBlockWithData(room.room_type, room.ac_type, room.price_npr);
                        });
                    } else {
                        container.innerHTML = '<p style="color: #666; font-style: italic;">No rooms added yet. Click "Add Room" to add rooms.</p>';
                    }
                    updateEditRoomsJson();
                })
                .catch(error => {
                    console.error('Error loading rooms:', error);
                    container.innerHTML = '<p style="color: #dc3545;">Error loading rooms. Please try again.</p>';
                });
        }
        
        // Add a room block with existing data for editing
        function addEditRoomBlockWithData(roomType, acType, priceNpr) {
            editRoomBlockCounter++;
            const container = document.getElementById('editRoomBlocksContainer');
            
            // Remove "no rooms" message if present
            const noRoomsMsg = container.querySelector('p');
            if (noRoomsMsg) noRoomsMsg.remove();
            
            const roomBlock = document.createElement('div');
            roomBlock.id = 'editRoomBlock_' + editRoomBlockCounter;
            roomBlock.style.border = '1px solid #e0e0e0';
            roomBlock.style.borderRadius = '0.5rem';
            roomBlock.style.padding = '1rem';
            roomBlock.style.backgroundColor = '#f9f9f9';
            roomBlock.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Room Type *</label>
                        <input type="text" class="form-control edit-room-type" placeholder="e.g., Deluxe, Standard" 
                               pattern="^.{2,50}$" 
                               title="Room type must be 2-50 characters"
                               value="${roomType || ''}"
                               required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">AC Type *</label>
                        <select class="form-control edit-ac-type" required>
                            <option value="">Select</option>
                            <option value="AC" ${acType === 'AC' ? 'selected' : ''}>AC</option>
                            <option value="Non-AC" ${acType === 'Non-AC' ? 'selected' : ''}>Non-AC</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Price (NPR) *</label>
                        <input type="number" class="form-control edit-room-price" step="0.01" min="1" max="9999999.99" 
                               placeholder="0.00" 
                               title="Price must be between NPR 1 and 9,999,999.99"
                               value="${priceNpr || ''}"
                               required>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeEditRoomBlock('editRoomBlock_${editRoomBlockCounter}')">Remove</button>
                    </div>
                </div>
            `;
            container.appendChild(roomBlock);
            
            // Add change listeners
            roomBlock.querySelector('.edit-room-type').addEventListener('input', updateEditRoomsJson);
            roomBlock.querySelector('.edit-ac-type').addEventListener('change', updateEditRoomsJson);
            roomBlock.querySelector('.edit-room-price').addEventListener('input', updateEditRoomsJson);
        }
        
        // Add empty room block for editing
        function addEditRoomBlock() {
            addEditRoomBlockWithData('', '', '');
            updateEditRoomsJson();
        }
        
        // Remove room block from edit modal
        function removeEditRoomBlock(blockId) {
            document.getElementById(blockId).remove();
            updateEditRoomsJson();
            
            // Show "no rooms" message if container is empty
            const container = document.getElementById('editRoomBlocksContainer');
            if (container.children.length === 0) {
                container.innerHTML = '<p style="color: #666; font-style: italic;">No rooms. Click "Add Room" to add rooms.</p>';
            }
        }
        
        // Update hidden JSON field with room data for edit form
        function updateEditRoomsJson() {
            const container = document.getElementById('editRoomBlocksContainer');
            const roomBlocks = container.querySelectorAll('[id^="editRoomBlock_"]');
            const rooms = [];
            
            roomBlocks.forEach(block => {
                const roomType = block.querySelector('.edit-room-type').value.trim();
                const acType = block.querySelector('.edit-ac-type').value;
                const price = block.querySelector('.edit-room-price').value;
                
                if (roomType && acType && price) {
                    rooms.push({
                        room_type: roomType,
                        ac_type: acType,
                        price_npr: parseFloat(price),
                        available: 1
                    });
                }
            });
            
            document.getElementById('edit_rooms_json').value = JSON.stringify(rooms);
        }
        
        // Preview multiple images
        function previewMultipleImages(input, previewContainerId) {
            const previewContainer = document.getElementById(previewContainerId);
            previewContainer.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    
                    reader.addEventListener('load', function(e) {
                        const imgDiv = document.createElement('div');
                        imgDiv.style.position = 'relative';
                        imgDiv.style.border = '2px solid #e0e0e0';
                        imgDiv.style.borderRadius = '0.5rem';
                        imgDiv.style.overflow = 'hidden';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '100%';
                        img.style.height = '150px';
                        img.style.objectFit = 'cover';
                        
                        const fileName = document.createElement('p');
                        fileName.textContent = file.name;
                        fileName.style.margin = '0.5rem';
                        fileName.style.fontSize = '0.8rem';
                        fileName.style.color = '#666';
                        fileName.style.textAlign = 'center';
                        fileName.style.overflow = 'hidden';
                        fileName.style.textOverflow = 'ellipsis';
                        fileName.style.whiteSpace = 'nowrap';
                        
                        imgDiv.appendChild(img);
                        imgDiv.appendChild(fileName);
                        previewContainer.appendChild(imgDiv);
                    });
                    
                    reader.readAsDataURL(file);
                });
            }
        }
        
        // Inline day-wise blocks for itinerary creation
        const dayBlocksContainer = document.getElementById('dayBlocksContainer');
        const itineraryDaysInput = document.getElementById('itinerary_days_json');
        
        function renderInlineDays() {
            if (!dayBlocksContainer || !itineraryDaysInput) return;
            const days = Array.from(dayBlocksContainer.querySelectorAll('.day-block')).map(block => ({
                day_number: parseInt(block.querySelector('.day-number').value, 10),
                day_title: block.querySelector('.day-title').value.trim(),
                day_description: block.querySelector('.day-description').value.trim(),
                activities: block.querySelector('.day-activities').value.trim(),
                accommodation: block.querySelector('.day-accommodation').value.trim(),
                meals: block.querySelector('.day-meals').value.trim()
            })).filter(d => d.day_number && d.day_title);
            itineraryDaysInput.value = JSON.stringify(days);
        }
        
        function addDayBlock() {
            if (!dayBlocksContainer) return;
            const block = document.createElement('div');
            block.className = 'day-block';
            block.style.border = '1px solid #e0e0e0';
            block.style.borderRadius = '0.5rem';
            block.style.padding = '0.75rem';
            block.style.background = '#fafafa';
            
            const nextDay = dayBlocksContainer.children.length + 1;
            
            block.innerHTML = `
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center; margin-bottom:0.5rem;">
                    <label style="min-width:80px; margin:0;">Day #</label>
                    <input type="number" class="form-control day-number" value="${nextDay}" min="1" required style="width:120px;">
                    <label style="min-width:80px; margin:0;">Title</label>
                    <input type="text" class="form-control day-title" placeholder="Day title" required style="flex:1; min-width:200px;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.day-block').remove(); renderInlineDays();">Remove</button>
                </div>
                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label>Description</label>
                    <textarea class="form-control day-description" rows="2" placeholder="Short description"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:0.5rem;">
                    <label>Activities</label>
                    <textarea class="form-control day-activities" rows="2" placeholder="Activities for the day"></textarea>
                </div>
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                    <div style="flex:1; min-width:180px;">
                        <label>Accommodation</label>
                        <input type="text" class="form-control day-accommodation" placeholder="Hotel or stay">
                    </div>
                    <div style="flex:1; min-width:180px;">
                        <label>Meals</label>
                        <input type="text" class="form-control day-meals" placeholder="Breakfast, Lunch...">
                    </div>
                </div>
            `;
            
            block.querySelectorAll('input, textarea').forEach(el => {
                el.addEventListener('input', renderInlineDays);
            });
            
            dayBlocksContainer.appendChild(block);
            renderInlineDays();
        }
        
        function clearDayBlocks() {
            if (!dayBlocksContainer) return;
            dayBlocksContainer.innerHTML = '';
            renderInlineDays();
        }
        
        // Keep JSON synced on submit
        const itineraryForm = document.getElementById('itineraryForm');
        if (itineraryForm) {
            itineraryForm.addEventListener('submit', function() {
                renderInlineDays();
            });
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Form validation functions
        function validateForm(formId) {
            const form = document.getElementById(formId);
            if (!form) return true;
            
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });
            
            return isValid;
        }
        
        function validateField(field) {
            const value = field.value.trim();
            const fieldId = field.id;
            const errorElement = document.getElementById(fieldId + '_error');
            
            // Clear previous error
            clearFieldError(field);
            
            // Required field check
            if (field.hasAttribute('required') && !value) {
                showFieldError(field, errorElement, 'This field is required');
                return false;
            }
            
            // Skip validation if field is empty and not required
            if (!value && !field.hasAttribute('required')) {
                return true;
            }
            
            // Pattern validation
            if (field.hasAttribute('pattern')) {
                const pattern = new RegExp(field.getAttribute('pattern'));
                if (!pattern.test(value)) {
                    const title = field.getAttribute('title') || 'Invalid format';
                    showFieldError(field, errorElement, title);
                    return false;
                }
            }
            
            // Min/Max length validation
            if (field.hasAttribute('minlength') && value.length < parseInt(field.getAttribute('minlength'))) {
                showFieldError(field, errorElement, `Minimum ${field.getAttribute('minlength')} characters required`);
                return false;
            }
            
            if (field.hasAttribute('maxlength') && value.length > parseInt(field.getAttribute('maxlength'))) {
                showFieldError(field, errorElement, `Maximum ${field.getAttribute('maxlength')} characters allowed`);
                return false;
            }
            
            // Type-specific validation
            if (field.type === 'email' && value && !isValidEmail(value)) {
                showFieldError(field, errorElement, 'Please enter a valid email address');
                return false;
            }
            
            if (field.type === 'number') {
                const numValue = parseFloat(value);
                if (isNaN(numValue)) {
                    showFieldError(field, errorElement, 'Please enter a valid number');
                    return false;
                }
                if (field.hasAttribute('min') && numValue < parseFloat(field.getAttribute('min'))) {
                    showFieldError(field, errorElement, `Minimum value is ${field.getAttribute('min')}`);
                    return false;
                }
                if (field.hasAttribute('max') && numValue > parseFloat(field.getAttribute('max'))) {
                    showFieldError(field, errorElement, `Maximum value is ${field.getAttribute('max')}`);
                    return false;
                }
            }
            
            // Custom validations based on field ID
            if (fieldId === 'user_name') {
                if (/^\s/.test(value)) {
                    showFieldError(field, errorElement, 'Name cannot start with a space');
                    return false;
                }
                if (/\s{3,}/.test(value)) {
                    showFieldError(field, errorElement, 'Name cannot have more than two consecutive spaces');
                    return false;
                }
                if (/[0-9]/.test(value)) {
                    showFieldError(field, errorElement, 'Name cannot contain numbers');
                    return false;
                }
                if (/[^\w\s]/.test(value)) {
                    showFieldError(field, errorElement, 'Name cannot contain special characters');
                    return false;
                }
            }
            
            if (fieldId === 'user_password' && value) {
                if (value.length < 8) {
                    showFieldError(field, errorElement, 'Password must be at least 8 characters');
                    return false;
                }
                if (!/[A-Z]/.test(value)) {
                    showFieldError(field, errorElement, 'Password must contain at least one uppercase letter');
                    return false;
                }
                if (!/[a-z]/.test(value)) {
                    showFieldError(field, errorElement, 'Password must contain at least one lowercase letter');
                    return false;
                }
                if (!/[0-9]/.test(value)) {
                    showFieldError(field, errorElement, 'Password must contain at least one number');
                    return false;
                }
                if (!/[^\w]/.test(value)) {
                    showFieldError(field, errorElement, 'Password must contain at least one special character');
                    return false;
                }
                if (/\s/.test(value)) {
                    showFieldError(field, errorElement, 'Password cannot contain spaces');
                    return false;
                }
            }
            
            if (fieldId === 'user_phone' && value) {
                if (!/^(97|98)[0-9]{8}$/.test(value)) {
                    showFieldError(field, errorElement, 'Phone must start with 97 or 98 and be exactly 10 digits');
                    return false;
                }
            }
            
            if (fieldId === 'user_email' && value) {
                if (!/^[a-zA-Z]/.test(value)) {
                    showFieldError(field, errorElement, 'Email must start with a letter');
                    return false;
                }
                const localPart = value.split('@')[0];
                if ((localPart.match(/\./g) || []).length > 1) {
                    showFieldError(field, errorElement, 'Email can only contain one dot before @');
                    return false;
                }
            }
            
            return true;
        }
        
        function isValidEmail(email) {
            const emailRegex = /^[a-zA-Z][a-zA-Z0-9_\-]*(\.[a-zA-Z0-9_\-]+)*@[a-zA-Z]+\.[a-zA-Z]{2,}$/;
            return emailRegex.test(email);
        }
        
        function showFieldError(field, errorElement, message) {
            field.style.borderColor = '#dc3545';
            field.classList.add('error');
            if (errorElement) {
                errorElement.textContent = '❌ ' + message;
                errorElement.style.display = 'block';
            } else {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'field-error';
                errorDiv.id = field.id + '_error';
                errorDiv.textContent = '❌ ' + message;
                field.parentNode.appendChild(errorDiv);
            }
        }
        
        function clearFieldError(field) {
            field.style.borderColor = '';
            field.classList.remove('error');
            const errorElement = document.getElementById(field.id + '_error');
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
            }
        }
        
            // Initialize real-time validation for all forms
        document.addEventListener('DOMContentLoaded', function() {
            // Real-time validation on input/change
            const forms = ['itineraryForm', 'hotelForm', 'editHotelForm', 'userForm', 'addDayForm', 'destinationForm', 'activityForm', 'editActivityForm'];
            forms.forEach(formId => {
                const form = document.getElementById(formId);
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (!validateForm(formId)) {
                            e.preventDefault();
                            // Scroll to first error
                            const firstError = form.querySelector('.error');
                            if (firstError) {
                                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }
                    });
                    
                    // Add real-time validation to all inputs
                    form.querySelectorAll('input, textarea, select').forEach(field => {
                        field.addEventListener('input', function() {
                            validateField(this);
                        });
                        
                        field.addEventListener('blur', function() {
                            validateField(this);
                        });
                    });
                }
            });
            
            // Activity form validation (no ID, find by form in modal)
            const activityForm = document.querySelector('#activityModal form');
            if (activityForm) {
                activityForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    activityForm.querySelectorAll('input[required], textarea[required]').forEach(field => {
                        if (!validateField(field)) {
                            isValid = false;
                        }
                    });
                    if (!isValid) {
                        e.preventDefault();
                        const firstError = activityForm.querySelector('.error');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                });
                
                activityForm.querySelectorAll('input, textarea').forEach(field => {
                    field.addEventListener('input', function() {
                        validateField(this);
                    });
                    field.addEventListener('blur', function() {
                        validateField(this);
                    });
                });
            }
        });
        
        // Day Management Functions
        function openDayModal(itineraryId, itineraryTitle) {
            document.getElementById('dayItineraryId').value = itineraryId;
            document.getElementById('dayModalTitle').textContent = 'Manage Days - ' + itineraryTitle;
            document.getElementById('dayModal').style.display = 'flex';
            loadItineraryDays(itineraryId);
            calculateNextDayNumber(itineraryId);
        }
        
        function calculateNextDayNumber(itineraryId) {
            fetch('get_itinerary_days.php?itinerary_id=' + itineraryId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.days.length > 0) {
                        const maxDay = Math.max(...data.days.map(d => d.day_number));
                        document.getElementById('day_number').value = maxDay + 1;
                    } else {
                        document.getElementById('day_number').value = 1;
                    }
                })
                .catch(error => {
                    document.getElementById('day_number').value = 1;
                });
        }
        
        function loadItineraryDays(itineraryId) {
            const existingDaysDiv = document.getElementById('existingDays');
            existingDaysDiv.innerHTML = '<p>Loading days...</p>';
            
            fetch('get_itinerary_days.php?itinerary_id=' + itineraryId)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.days.length > 0) {
                        let html = '<h4>Existing Days</h4><div style="display: grid; gap: 1rem; margin-bottom: 1rem;">';
                        data.days.forEach(day => {
                            html += `
                                <div style="border: 1px solid #e0e0e0; border-radius: 0.5rem; padding: 1rem; background: #f9f9f9;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                        <h5 style="margin: 0; color: #031881;">Day ${day.day_number}: ${day.day_title}</h5>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this day?');">
                                            <input type="hidden" name="delete_itinerary_day" value="1">
                                            <input type="hidden" name="day_id" value="${day.id}">
                                            <button type="submit" class="btn btn-danger btn-sm" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">Delete</button>
                                        </form>
                                    </div>
                                    ${day.day_description ? '<p style="margin: 0.5rem 0; color: #666;">' + day.day_description.substring(0, 100) + (day.day_description.length > 100 ? '...' : '') + '</p>' : ''}
                                    ${day.activities ? '<p style="margin: 0.5rem 0; font-size: 0.9rem;"><strong>Activities:</strong> ' + day.activities.substring(0, 80) + (day.activities.length > 80 ? '...' : '') + '</p>' : ''}
                                    ${day.accommodation ? '<p style="margin: 0.5rem 0; font-size: 0.9rem;"><strong>Accommodation:</strong> ' + day.accommodation + '</p>' : ''}
                                    ${day.meals ? '<p style="margin: 0.5rem 0; font-size: 0.9rem;"><strong>Meals:</strong> ' + day.meals + '</p>' : ''}
                                </div>
                            `;
                        });
                        html += '</div>';
                        existingDaysDiv.innerHTML = html;
                    } else {
                        existingDaysDiv.innerHTML = '<p style="color: #666;">No days added yet. Add your first day below.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading days:', error);
                    existingDaysDiv.innerHTML = '<p style="color: #dc3545;">Error loading days. Please try again.</p>';
                });
        }
        
        // Handle form submission to reload days after adding
        document.getElementById('addDayForm')?.addEventListener('submit', function(e) {
            // Form will submit normally, then page will reload with success message
            // Days will be reloaded on next modal open
        });

        // Room Management Functions for Hotel Modal
        let roomBlockCounter = 0;
        
        function addRoomBlock() {
            roomBlockCounter++;
            const container = document.getElementById('roomBlocksContainer');
            const roomBlock = document.createElement('div');
            roomBlock.id = 'roomBlock_' + roomBlockCounter;
            roomBlock.style.border = '1px solid #e0e0e0';
            roomBlock.style.borderRadius = '0.5rem';
            roomBlock.style.padding = '1rem';
            roomBlock.style.backgroundColor = '#f9f9f9';
            roomBlock.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 100px auto; gap: 1rem; align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Room Type *</label>
                        <input type="text" class="form-control room-type" placeholder="e.g., Deluxe, Standard" 
                               pattern="^.{2,50}$" 
                               title="Room type must be 2-50 characters"
                               required>
                        <div class="field-error room-type-error-${roomBlockCounter}" style="display: none;"></div>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">AC Type *</label>
                        <select class="form-control ac-type" required>
                            <option value="">Select</option>
                            <option value="AC">AC</option>
                            <option value="Non-AC">Non-AC</option>
                        </select>
                        <div class="field-error ac-type-error-${roomBlockCounter}" style="display: none;"></div>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Price (NPR) *</label>
                        <input type="number" class="form-control room-price" step="0.01" min="1" max="9999999.99" 
                               placeholder="0.00" 
                               title="Price must be between NPR 1 and 9,999,999.99"
                               required>
                        <div class="field-error room-price-error-${roomBlockCounter}" style="display: none;"></div>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Quantity *</label>
                        <input type="number" class="form-control room-quantity" min="1" max="999" 
                               placeholder="1" 
                               title="Number of rooms available"
                               required value="1">
                        <div class="field-error room-quantity-error-${roomBlockCounter}" style="display: none;"></div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRoomBlock('roomBlock_${roomBlockCounter}')">Remove</button>
                    </div>
                </div>
            `;
            container.appendChild(roomBlock);
            
            // Add validation listeners to new room block
            const roomTypeInput = roomBlock.querySelector('.room-type');
            const acTypeSelect = roomBlock.querySelector('.ac-type');
            const roomPriceInput = roomBlock.querySelector('.room-price');
            const roomQuantityInput = roomBlock.querySelector('.room-quantity');
            
            roomTypeInput.addEventListener('input', function() {
                validateRoomField(this, 'room-type-error-' + roomBlockCounter);
                updateHotelRoomsJson();
            });
            roomTypeInput.addEventListener('blur', function() {
                validateRoomField(this, 'room-type-error-' + roomBlockCounter);
            });
            
            acTypeSelect.addEventListener('change', function() {
                validateRoomField(this, 'ac-type-error-' + roomBlockCounter);
                updateHotelRoomsJson();
            });
            
            roomPriceInput.addEventListener('input', function() {
                validateRoomField(this, 'room-price-error-' + roomBlockCounter);
                updateHotelRoomsJson();
            });
            roomPriceInput.addEventListener('blur', function() {
                validateRoomField(this, 'room-price-error-' + roomBlockCounter);
            });

            roomQuantityInput.addEventListener('input', function() {
                validateRoomField(this, 'room-quantity-error-' + roomBlockCounter);
                updateHotelRoomsJson();
            });
            roomQuantityInput.addEventListener('blur', function() {
                validateRoomField(this, 'room-quantity-error-' + roomBlockCounter);
            });
            
            updateHotelRoomsJson();
        }
        
        function validateRoomField(field, errorId) {
            const value = field.value.trim();
            const errorElement = field.parentNode.querySelector('.' + errorId);
            
            if (field.hasAttribute('required') && !value) {
                showRoomFieldError(field, errorElement, 'This field is required');
                return false;
            }
            
            if (!value && !field.hasAttribute('required')) {
                clearRoomFieldError(field, errorElement);
                return true;
            }
            
            if (field.type === 'number' && value) {
                const numValue = parseFloat(value);
                if (isNaN(numValue) || numValue < 1) {
                    showRoomFieldError(field, errorElement, 'Price must be at least NPR 1');
                    return false;
                }
                if (numValue > 9999999.99) {
                    showRoomFieldError(field, errorElement, 'Price cannot exceed NPR 9,999,999.99');
                    return false;
                }
            }
            
            if (field.hasAttribute('pattern')) {
                const pattern = new RegExp(field.getAttribute('pattern'));
                if (!pattern.test(value)) {
                    showRoomFieldError(field, errorElement, field.getAttribute('title') || 'Invalid format');
                    return false;
                }
            }
            
            clearRoomFieldError(field, errorElement);
            return true;
        }
        
        function showRoomFieldError(field, errorElement, message) {
            field.style.borderColor = '#dc3545';
            if (errorElement) {
                errorElement.textContent = '❌ ' + message;
                errorElement.style.display = 'block';
            }
        }
        
        function clearRoomFieldError(field, errorElement) {
            field.style.borderColor = '';
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
            }
        }
        
        function removeRoomBlock(blockId) {
            document.getElementById(blockId).remove();
            updateHotelRoomsJson();
        }
        
        function updateHotelRoomsJson() {
            const container = document.getElementById('roomBlocksContainer');
            const roomBlocks = container.querySelectorAll('[id^="roomBlock_"]');
            const rooms = [];
            
            roomBlocks.forEach(block => {
                const roomType = block.querySelector('.room-type').value.trim();
                const acType = block.querySelector('.ac-type').value;
                const price = block.querySelector('.room-price').value;
                const quantity = block.querySelector('.room-quantity').value;
                
                if (roomType && acType && price && quantity) {
                    rooms.push({
                        room_type: roomType,
                        ac_type: acType,
                        price_npr: parseFloat(price),
                        quantity: parseInt(quantity),
                        available: 1
                    });
                }
            });
            
            document.getElementById('hotel_rooms_json').value = JSON.stringify(rooms);
        }
        
        // Add event listeners to room inputs
        document.addEventListener('DOMContentLoaded', function() {
            const roomContainer = document.getElementById('roomBlocksContainer');
            if (roomContainer) {
                roomContainer.addEventListener('input', updateHotelRoomsJson);
                roomContainer.addEventListener('change', updateHotelRoomsJson);
            }
            
            // Handle hotel form submission
            const hotelForm = document.getElementById('hotelForm');
            if (hotelForm) {
                hotelForm.addEventListener('submit', function(e) {
                    updateHotelRoomsJson();
                });
            }
        });

        // Day Block Management Functions for Itinerary Modal
        let dayBlockCounter = 0;
        
        function addDayBlock() {
            dayBlockCounter++;
            const container = document.getElementById('dayBlocksContainer');
            const dayBlock = document.createElement('div');
            dayBlock.id = 'dayBlock_' + dayBlockCounter;
            dayBlock.style.border = '1px solid #e0e0e0';
            dayBlock.style.borderRadius = '0.5rem';
            dayBlock.style.padding = '1rem';
            dayBlock.style.backgroundColor = '#f9f9f9';
            dayBlock.innerHTML = `
                <div style="display: grid; gap: 1rem;">
                    <div style="display: grid; grid-template-columns: 100px 1fr auto; gap: 1rem; align-items: start;">
                        <div>
                            <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Day # *</label>
                            <input type="number" class="form-control day-number" min="1" max="365" placeholder="1" 
                                   title="Day number must be between 1 and 365"
                                   required>
                            <div class="field-error day-number-error-${dayBlockCounter}" style="display: none; font-size: 0.75rem;"></div>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Day Title *</label>
                            <input type="text" class="form-control day-title" placeholder="e.g., Arrival Day" 
                                   pattern="^.{3,100}$" 
                                   title="Day title must be 3-100 characters"
                                   required>
                            <div class="field-error day-title-error-${dayBlockCounter}" style="display: none; font-size: 0.75rem;"></div>
                        </div>
                        <div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeDayBlock('dayBlock_${dayBlockCounter}')">Remove</button>
                        </div>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Description</label>
                        <textarea class="form-control day-description" rows="2" placeholder="Day description..."></textarea>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Activities</label>
                            <textarea class="form-control day-activities" rows="2" placeholder="Activities for this day"></textarea>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Accommodation</label>
                            <input type="text" class="form-control day-accommodation" placeholder="Hotel name">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.3rem; font-weight: 600;">Meals</label>
                            <input type="text" class="form-control day-meals" placeholder="e.g., Breakfast, Lunch">
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(dayBlock);
            
            // Add validation listeners to new day block
            const dayNumberInput = dayBlock.querySelector('.day-number');
            const dayTitleInput = dayBlock.querySelector('.day-title');
            
            dayNumberInput.addEventListener('input', function() {
                validateDayField(this, 'day-number-error-' + dayBlockCounter);
                updateItineraryDaysJson();
            });
            dayNumberInput.addEventListener('blur', function() {
                validateDayField(this, 'day-number-error-' + dayBlockCounter);
            });
            
            dayTitleInput.addEventListener('input', function() {
                validateDayField(this, 'day-title-error-' + dayBlockCounter);
                updateItineraryDaysJson();
            });
            dayTitleInput.addEventListener('blur', function() {
                validateDayField(this, 'day-title-error-' + dayBlockCounter);
            });
            
            updateItineraryDaysJson();
        }
        
        function validateDayField(field, errorId) {
            const value = field.value.trim();
            const errorElement = field.parentNode.querySelector('.' + errorId);
            
            if (field.hasAttribute('required') && !value) {
                showDayFieldError(field, errorElement, 'This field is required');
                return false;
            }
            
            if (!value && !field.hasAttribute('required')) {
                clearDayFieldError(field, errorElement);
                return true;
            }
            
            if (field.type === 'number' && value) {
                const numValue = parseInt(value);
                if (isNaN(numValue) || numValue < 1 || numValue > 365) {
                    showDayFieldError(field, errorElement, 'Day number must be between 1 and 365');
                    return false;
                }
            }
            
            if (field.hasAttribute('pattern')) {
                const pattern = new RegExp(field.getAttribute('pattern'));
                if (!pattern.test(value)) {
                    showDayFieldError(field, errorElement, field.getAttribute('title') || 'Invalid format');
                    return false;
                }
            }
            
            clearDayFieldError(field, errorElement);
            return true;
        }
        
        function showDayFieldError(field, errorElement, message) {
            field.style.borderColor = '#dc3545';
            if (errorElement) {
                errorElement.textContent = '❌ ' + message;
                errorElement.style.display = 'block';
            }
        }
        
        function clearDayFieldError(field, errorElement) {
            field.style.borderColor = '';
            if (errorElement) {
                errorElement.textContent = '';
                errorElement.style.display = 'none';
            }
        }
        
        function removeDayBlock(blockId) {
            document.getElementById(blockId).remove();
            updateItineraryDaysJson();
        }
        
        function clearDayBlocks() {
            const container = document.getElementById('dayBlocksContainer');
            container.innerHTML = '';
            dayBlockCounter = 0;
            updateItineraryDaysJson();
        }
        
        function updateItineraryDaysJson() {
            const container = document.getElementById('dayBlocksContainer');
            const dayBlocks = container.querySelectorAll('[id^="dayBlock_"]');
            const days = [];
            
            dayBlocks.forEach(block => {
                const dayNumber = block.querySelector('.day-number').value;
                const dayTitle = block.querySelector('.day-title').value.trim();
                
                if (dayNumber && dayTitle) {
                    days.push({
                        day_number: parseInt(dayNumber),
                        day_title: dayTitle,
                        day_description: block.querySelector('.day-description').value.trim(),
                        activities: block.querySelector('.day-activities').value.trim(),
                        accommodation: block.querySelector('.day-accommodation').value.trim(),
                        meals: block.querySelector('.day-meals').value.trim()
                    });
                }
            });
            
            document.getElementById('itinerary_days_json').value = JSON.stringify(days);
        }
        
        // Add event listeners to day inputs
        document.addEventListener('DOMContentLoaded', function() {
            const dayContainer = document.getElementById('dayBlocksContainer');
            if (dayContainer) {
                dayContainer.addEventListener('input', updateItineraryDaysJson);
                dayContainer.addEventListener('change', updateItineraryDaysJson);
            }
            
            // Handle itinerary form submission
            const itineraryForm = document.getElementById('itineraryForm');
            if (itineraryForm) {
                itineraryForm.addEventListener('submit', function(e) {
                    updateItineraryDaysJson();
                });
            }
            
            // Initialize all charts
            initializeAllCharts();
        });
        
        // Initialize all dashboard charts
        function initializeAllCharts() {
            // Bookings Type Comparison Chart
            const bookingsTypeCtx = document.getElementById('bookingsTypeChart');
            if (bookingsTypeCtx) {
                new Chart(bookingsTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Hotel Bookings', 'Activity Bookings'],
                        datasets: [{
                            data: [<?php echo $hotel_bookings_count; ?>, <?php echo $activity_bookings_count; ?>],
                            backgroundColor: ['rgba(111, 126, 203, 0.8)', 'rgba(255, 87, 34, 0.8)'],
                            borderColor: ['rgba(111, 126, 203, 1)', 'rgba(255, 87, 34, 1)'],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.parsed + ' booking(s)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
            // Booking Status Breakdown Chart
            const bookingStatusCtx = document.getElementById('bookingStatusChart');
            if (bookingStatusCtx) {
                new Chart(bookingStatusCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Pending', 'Approved', 'Rejected'],
                        datasets: [{
                            data: [
                                <?php echo $booking_status_stats['pending']; ?>,
                                <?php echo $booking_status_stats['approved']; ?>,
                                <?php echo $booking_status_stats['rejected']; ?>
                            ],
                            backgroundColor: [
                                'rgba(255, 193, 7, 0.8)',
                                'rgba(40, 167, 69, 0.8)',
                                'rgba(220, 53, 69, 0.8)'
                            ],
                            borderColor: [
                                'rgba(255, 193, 7, 1)',
                                'rgba(40, 167, 69, 1)',
                                'rgba(220, 53, 69, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.parsed + ' booking(s)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
            
          
            // Revenue Breakdown Chart
            const revenueCtx = document.getElementById('revenueChart');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: ['Hotel Bookings', 'Activity Bookings'],
                        datasets: [{
                            label: 'Revenue (NPR)',
                            data: [
                                <?php echo $total_revenue_hotel; ?>,
                                <?php echo $total_revenue_activity; ?>
                            ],
                            backgroundColor: [
                                'rgba(111, 126, 203, 0.8)',
                                'rgba(255, 87, 34, 0.8)'
                            ],
                            borderColor: [
                                'rgba(111, 126, 203, 1)',
                                'rgba(255, 87, 34, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Revenue: NPR ' + context.parsed.y.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return 'NPR ' + value.toLocaleString('en-IN');
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }
        
        // Open Edit Activity Modal
        function openEditActivityModal(id, name, description, price, status, imagePath) {
            document.getElementById('edit_activity_id').value = id;
            document.getElementById('edit_activity_name').value = name;
            document.getElementById('edit_activity_description').value = description;
            document.getElementById('edit_activity_price_npr').value = price;
            document.getElementById('edit_activity_status').value = status;
            
            // Handle image preview
            const currentImage = document.getElementById('current_activity_image');
            const noCurrentImage = document.getElementById('no_current_activity_image');
            const newImagePreview = document.getElementById('editActivityPreview');
            const fileInput = document.getElementById('edit_activity_image');
            
            // Reset file input
            fileInput.value = '';
            newImagePreview.style.display = 'none';
            newImagePreview.src = '#';
            
            if (imagePath) {
                currentImage.src = imagePath;
                currentImage.style.display = 'block';
                noCurrentImage.style.display = 'none';
            } else {
                currentImage.style.display = 'none';
                noCurrentImage.style.display = 'block';
            }
            
            // Clear errors
            document.querySelectorAll('.field-error').forEach(el => el.textContent = '');
            
            // Open Modal - use flex to center it
            document.getElementById('editActivityModal').style.display = 'flex';
        }
        
        // ✅ Client-side Image Validation Function (matches server-side validation)
        function validateImageFile(input) {
            const allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
            const files = input.files;
            
            if (!files || files.length === 0) {
                return { valid: true, message: '' }; // No file selected is okay
            }
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const fileName = file.name.toLowerCase();
                const fileExtension = fileName.split('.').pop();
                
                // Check if extension is allowed
                if (!allowedExtensions.includes(fileExtension)) {
                    return {
                        valid: false,
                        message: `❌ Invalid file type: "${file.name}". Only jpg, jpeg, png, and webp are allowed.`
                    };
                }
            }
            
            return { valid: true, message: '✅ Valid image file(s)' };
        }
        
        // ✅ Add real-time validation to all file inputs
        document.addEventListener('DOMContentLoaded', function() {
            // Get all file inputs with accept="image/*"
            const fileInputs = document.querySelectorAll('input[type="file"][accept="image/*"]');
            
            fileInputs.forEach(function(input) {
                // Create error message element if it doesn't exist
                let errorElement = input.nextElementSibling;
                if (!errorElement || !errorElement.classList.contains('file-error')) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'file-error';
                    errorElement.style.color = 'red';
                    errorElement.style.fontSize = '0.9rem';
                    errorElement.style.marginTop = '0.5rem';
                    input.parentNode.insertBefore(errorElement, input.nextSibling);
                }
                
                // Add change event listener
                input.addEventListener('change', function() {
                    const validation = validateImageFile(this);
                    const errorDiv = this.nextElementSibling;
                    
                    if (!validation.valid) {
                        errorDiv.textContent = validation.message;
                        errorDiv.style.color = 'red';
                        this.value = ''; // Clear the invalid file
                        
                        // Clear preview if exists
                        const previewId = this.getAttribute('onchange')?.match(/previewImage.*'([^']+)'/)?.[1] ||
                                        this.getAttribute('onchange')?.match(/previewMultipleImages.*'([^']+)'/)?.[1];
                        if (previewId) {
                            const previewElement = document.getElementById(previewId);
                            if (previewElement) {
                                if (previewElement.tagName === 'IMG') {
                                    previewElement.style.display = 'none';
                                } else {
                                    previewElement.innerHTML = '';
                                }
                            }
                        }
                    } else if (this.files.length > 0) {
                        errorDiv.textContent = validation.message;
                        errorDiv.style.color = 'green';
                    } else {
                        errorDiv.textContent = '';
                    }
                });
            });
            
            // Add form submit validation
            const forms = document.querySelectorAll('form[enctype="multipart/form-data"]');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(e) {
                    const fileInputs = this.querySelectorAll('input[type="file"][accept="image/*"]');
                    let hasError = false;
                    
                    fileInputs.forEach(function(input) {
                        if (input.files.length > 0) {
                            const validation = validateImageFile(input);
                            if (!validation.valid) {
                                hasError = true;
                                alert(validation.message);
                                e.preventDefault();
                                input.focus();
                                return false;
                            }
                        }
                    });
                    
                    if (hasError) {
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>