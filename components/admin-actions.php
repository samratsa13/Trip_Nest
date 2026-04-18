<?php
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
                                    $quantity = $room['quantity'] ?? 1;
                                    $room_stmt->execute([
                                        $hotel_id,
                                        $room['room_type'],
                                        $room['ac_type'],
                                        $room['price_npr'],
                                        $quantity,
                                        $room['available'] ?? $quantity
                                    ]);
                                    
                                    // Auto-generate physical room units immediately for the stock overview
                                    $room_id = $pdo->lastInsertId();
                                    $type_initial = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $room['room_type']), 0, 1) ?: 'R');
                                    $ac_initial   = strtoupper(substr($room['ac_type'], 0, 1));
                                    $prefix = $type_initial . $ac_initial . "-R" . $room_id . "-";
                                    
                                    $rn_stmt = $pdo->prepare("INSERT IGNORE INTO room_numbers (hotel_id, room_id, room_number, status) VALUES (?, ?, ?, 'available')");
                                    for ($i = 1; $i <= $quantity; $i++) {
                                        $rn_stmt->execute([$hotel_id, $room_id, $prefix . $i]);
                                    }
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
        
        $pdo->prepare("DELETE FROM room_numbers WHERE hotel_id = ?")->execute([$hotel_id]);
        $pdo->prepare("DELETE FROM hotel_rooms WHERE hotel_id = ?")->execute([$hotel_id]);
        
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
                // Update rooms without breaking foreign references
                if (isset($_POST['edit_rooms_json'])) {
                    $rooms = json_decode($_POST['edit_rooms_json'], true);
                    if (is_array($rooms)) {
                        $existing_ids = [];
                        foreach ($rooms as $room) {
                            if (!empty($room['room_type']) && !empty($room['ac_type']) && !empty($room['price_npr'])) {
                                $quantity = $room['quantity'] ?? 1;
                                $room_id = (!empty($room['id'])) ? intval($room['id']) : 0;
                                
                                if ($room_id > 0) {
                                    // Update existing room including its new quantity
                                    $room_stmt = $pdo->prepare("UPDATE hotel_rooms SET room_type = ?, ac_type = ?, price_npr = ?, quantity = ?, available = ? WHERE id = ? AND hotel_id = ?");
                                    $room_stmt->execute([$room['room_type'], $room['ac_type'], $room['price_npr'], $quantity, $room['available'] ?? 1, $room_id, $hotel_id]);
                                    $existing_ids[] = $room_id;
                                    
                                    // We are no longer using room_numbers so we don't sync it.
                                } else {
                                    // Insert new room
                                    $room_stmt = $pdo->prepare("INSERT INTO hotel_rooms (hotel_id, room_type, ac_type, price_npr, quantity, available) VALUES (?, ?, ?, ?, ?, ?)");
                                    $room_stmt->execute([$hotel_id, $room['room_type'], $room['ac_type'], $room['price_npr'], $quantity, $room['available'] ?? 1]);
                                    $existing_ids[] = $pdo->lastInsertId();
                                }
                            }
                        }
                        
                        // Disable removed rooms instead of hard deleting to prevent breaking existing bookings
                        if (!empty($existing_ids)) {
                            $placeholders = str_repeat('?,', count($existing_ids) - 1) . '?';
                            $del_params = array_merge([$hotel_id], $existing_ids);
                            $pdo->prepare("UPDATE hotel_rooms SET available = 0 WHERE hotel_id = ? AND id NOT IN ($placeholders)")->execute($del_params);
                        } else {
                            $pdo->prepare("UPDATE hotel_rooms SET available = 0 WHERE hotel_id = ?")->execute([$hotel_id]);
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
        $address = trim($_POST['user_address'] ?? '');
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
        
        if (empty($address)) {
            $errors[] = "Address is required";
        } elseif (!preg_match('/^[a-zA-Z0-9\s,\-]+$/', $address)) {
            $errors[] = "Address can only contain letters, numbers, spaces, commas, and hyphens";
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
                
                $stmt = $pdo->prepare("INSERT INTO users (name, address, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$name, $address, $email, $phone, $hashed_password, $role])) {
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
