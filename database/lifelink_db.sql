-- LifeLink: Online Blood Donor Finder and Management System
-- Database Schema and Sample Data
-- Database Name: lifelink_db

CREATE DATABASE IF NOT EXISTS `lifelink_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `lifelink_db`;

-- 1. USERS TABLE
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT AUTO_INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('donor', 'requester', 'admin') NOT NULL DEFAULT 'donor',
  `account_status` ENUM('active', 'deactivated', 'suspended') DEFAULT 'active',
  `verification_status` ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. DONORS TABLE
CREATE TABLE IF NOT EXISTS `donors` (
  `donor_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNIQUE NOT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  `date_of_birth` DATE NULL,
  `gender` ENUM('Male', 'Female', 'Other') DEFAULT 'Male',
  `state` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `area` VARCHAR(100) NULL,
  `pin_code` VARCHAR(20) NOT NULL,
  `availability` ENUM('Available', 'Temporarily Unavailable', 'Not Accepting Requests') DEFAULT 'Available',
  `last_donation_date` DATE NULL,
  `profile_description` TEXT NULL,
  `badges` VARCHAR(255) DEFAULT 'First Donation',
  `hide_phone` TINYINT(1) DEFAULT 0,
  `screening_completed` TINYINT(1) DEFAULT 0,
  `screening_answers` TEXT NULL,
  `screening_date` TIMESTAMP NULL,
  `verification_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_donors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. BLOOD REQUESTS TABLE
CREATE TABLE IF NOT EXISTS `blood_requests` (
  `request_id` INT AUTO_INCREMENT PRIMARY KEY,
  `unique_request_number` VARCHAR(30) UNIQUE NOT NULL,
  `requester_id` INT NOT NULL,
  `patient_name` VARCHAR(100) NOT NULL,
  `blood_group` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
  `units_required` INT NOT NULL DEFAULT 1,
  `hospital_name` VARCHAR(150) NOT NULL,
  `hospital_address` TEXT NOT NULL,
  `state` VARCHAR(100) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `pin_code` VARCHAR(20) NOT NULL,
  `required_date` DATE NOT NULL,
  `urgency` ENUM('Normal', 'Urgent', 'Emergency') DEFAULT 'Normal',
  `additional_information` TEXT NULL,
  `contact_person` VARCHAR(100) NOT NULL,
  `contact_phone` VARCHAR(20) NOT NULL,
  `status` ENUM('Pending', 'Donors Notified', 'Donor Response Received', 'Accepted', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending',
  `hospital_confirmed` TINYINT(1) DEFAULT 0,
  `hospital_confirmed_at` TIMESTAMP NULL,
  `hospital_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_requests_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. REQUEST RESPONSES TABLE
CREATE TABLE IF NOT EXISTS `request_responses` (
  `response_id` INT AUTO_INCREMENT PRIMARY KEY,
  `request_id` INT NOT NULL,
  `donor_id` INT NOT NULL,
  `response_status` ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
  `response_message` TEXT NULL,
  `consent_reviewed` TINYINT(1) DEFAULT 1,
  `consent_interest` TINYINT(1) DEFAULT 1,
  `consent_medical_confirmation` TINYINT(1) DEFAULT 1,
  `consent_contact_sharing` TINYINT(1) DEFAULT 1,
  `consent_date` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `response_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_request_donor` (`request_id`, `donor_id`),
  CONSTRAINT `fk_responses_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_responses_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. DONATIONS TABLE
CREATE TABLE IF NOT EXISTS `donations` (
  `donation_id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_id` INT NOT NULL,
  `request_id` INT NULL,
  `donation_date` DATE NOT NULL,
  `hospital_name` VARCHAR(150) NOT NULL,
  `units_donated` INT DEFAULT 1,
  `verification_status` ENUM('Pending', 'Verified', 'Approved', 'Rejected') DEFAULT 'Pending',
  `verified_by` INT NULL,
  `verified_at` DATETIME NULL,
  `admin_notes` TEXT NULL,
  `certificate_no` VARCHAR(100) NULL,
  `submission_type` ENUM('manual', 'request_completion') DEFAULT 'manual',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_donations_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_donations_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donations_admin` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `notification_type` ENUM('request', 'response', 'status', 'system', 'emergency') DEFAULT 'system',
  `related_request_id` INT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. FAVOURITES TABLE
CREATE TABLE IF NOT EXISTS `favourites` (
  `favourite_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `donor_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_favourite` (`user_id`, `donor_id`),
  CONSTRAINT `fk_favourites_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favourites_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. FEEDBACK TABLE
CREATE TABLE IF NOT EXISTS `feedback` (
  `feedback_id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `rating` INT DEFAULT 5,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_feedback_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. REPORTS TABLE
CREATE TABLE IF NOT EXISTS `reports` (
  `report_id` INT AUTO_INCREMENT PRIMARY KEY,
  `reported_by` INT NOT NULL,
  `reported_user` INT NULL,
  `request_id` INT NULL,
  `report_reason` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('Pending', 'Reviewed', 'Resolved', 'Dismissed') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_reports_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_user` FOREIGN KEY (`reported_user`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. CONTACT MESSAGES TABLE
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `message_id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. ADMIN LOGS TABLE
CREATE TABLE IF NOT EXISTS `admin_logs` (
  `log_id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. PASSWORD RESETS TABLE (Secure Token-Based Password Recovery)
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  INDEX `idx_token_hash` (`token_hash`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. DONOR SCREENINGS TABLE (Recurring Health & Eligibility Reconfirmation)
CREATE TABLE IF NOT EXISTS `donor_screenings` (
  `screening_id` INT AUTO_INCREMENT PRIMARY KEY,
  `donor_id` INT NOT NULL,
  `request_id` INT NULL,
  `donated_recently` ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  `feeling_well` ENUM('Yes', 'No') NOT NULL DEFAULT 'Yes',
  `medical_advice` ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  `recent_medication_illness` ENUM('Yes', 'No') NOT NULL DEFAULT 'No',
  `willing_to_donate` ENUM('Yes', 'No') NOT NULL DEFAULT 'Yes',
  `answers_confirmed_accurate` TINYINT(1) DEFAULT 1,
  `eligibility_status` ENUM('Self_Reported_Eligible', 'Requires_Medical_Review') DEFAULT 'Self_Reported_Eligible',
  `notes` TEXT NULL,
  `screening_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_screening_donor` (`donor_id`),
  INDEX `idx_screening_request` (`request_id`),
  CONSTRAINT `fk_screenings_donor` FOREIGN KEY (`donor_id`) REFERENCES `donors` (`donor_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_screenings_request` FOREIGN KEY (`request_id`) REFERENCES `blood_requests` (`request_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA
-- Default password for all seed accounts is: Password@123 (except admin is Admin@123)
-- Admin: $2y$12$SDu6PFkXEbYlpZ7LbvG.M.IePdi43HC8uCtYPRkWws3om0BYtjBOu
-- Others: $2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu

-- 1. Insert Users
INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone`, `password`, `role`, `account_status`, `verification_status`) VALUES
(1, 'System Administrator', 'admin@lifelink.org', '+91 9876543210', '$2y$12$SDu6PFkXEbYlpZ7LbvG.M.IePdi43HC8uCtYPRkWws3om0BYtjBOu', 'admin', 'active', 'verified'),
(2, 'Sarah Jenkins', 'sarah.requester@lifelink.org', '+91 9822334455', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'requester', 'active', 'verified'),
(3, 'City Care Hospital', 'care.hospital@lifelink.org', '+91 9811223344', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'requester', 'active', 'verified'),
(4, 'Dr. Marcus Vance', 'marcus.vance@lifelink.org', '+91 9766554433', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(5, 'Aarav Sharma', 'aarav.donor@lifelink.org', '+91 9123456780', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(6, 'Priya Patel', 'priya.patel@lifelink.org', '+91 9123456781', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(7, 'Rohan Verma', 'rohan.verma@lifelink.org', '+91 9123456782', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(8, 'Ananya Iyer', 'ananya.iyer@lifelink.org', '+91 9123456783', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(9, 'Vikram Malhotra', 'vikram.m@lifelink.org', '+91 9123456784', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(10, 'Fatima Sheikh', 'fatima.s@lifelink.org', '+91 9123456785', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified'),
(11, 'Kavita Nair', 'kavita.nair@lifelink.org', '+91 9123456786', '$2y$12$Y8WAb4KMzob266qrBBkYP.k/GPkRsIqSUQM0gzMWjNzla/Ops.Ruu', 'donor', 'active', 'verified');

-- 2. Insert Donors Profile
INSERT INTO `donors` (`donor_id`, `user_id`, `blood_group`, `date_of_birth`, `gender`, `state`, `city`, `area`, `pin_code`, `availability`, `last_donation_date`, `profile_description`, `badges`, `hide_phone`, `screening_completed`, `screening_date`) VALUES
(1, 4, 'O-', '1990-05-14', 'Male', 'Maharashtra', 'Mumbai', 'Andheri West', '400053', 'Available', '2026-08-10', 'Universal donor willing to respond to emergency calls anywhere in Mumbai.', 'Verified Donor, Community Contributor', 0, 1, '2026-08-10 10:00:00'),
(2, 5, 'O+', '1995-11-20', 'Male', 'Maharashtra', 'Mumbai', 'Bandra', '400050', 'Available', '2026-09-02', 'Regular blood donor, committed to helping emergency trauma patients.', 'Verified Donor, First Donation', 0, 1, '2026-09-02 10:00:00'),
(3, 6, 'A+', '1998-03-15', 'Female', 'Karnataka', 'Bengaluru', 'Koramangala', '560034', 'Available', '2026-07-22', 'Healthy lifestyle, happy to donate whenever required.', 'Verified Donor, Community Contributor', 1, 1, '2026-07-22 10:00:00'),
(4, 7, 'B+', '1992-09-08', 'Male', 'Delhi', 'New Delhi', 'Connaught Place', '110001', 'Available', '2026-06-15', 'Ready for urgent hospital requests across NCR.', 'First Donation', 0, 1, '2026-06-15 10:00:00'),
(5, 8, 'AB+', '2000-01-25', 'Female', 'Tamil Nadu', 'Chennai', 'Adyar', '600020', 'Temporarily Unavailable', '2026-10-18', 'Currently recovering from seasonal flu; available from next month.', 'First Donation', 0, 1, '2026-10-18 10:00:00'),
(6, 9, 'A-', '1988-12-04', 'Male', 'Maharashtra', 'Pune', 'Kothrud', '411038', 'Available', '2026-05-19', 'Rare blood group donor. Always on standby for critical surgeries.', 'Verified Donor, Community Contributor', 0, 1, '2026-05-19 10:00:00'),
(7, 10, 'B-', '1996-07-30', 'Female', 'Telangana', 'Hyderabad', 'Madhapur', '500081', 'Available', '2026-04-12', 'Software engineer and voluntary blood donor.', 'Verified Donor', 0, 1, '2026-04-12 10:00:00'),
(8, 11, 'AB-', '1994-02-18', 'Female', 'West Bengal', 'Kolkata', 'Salt Lake', '700091', 'Not Accepting Requests', '2026-01-10', 'Currently out of town for business.', 'First Donation', 1, 1, '2026-01-10 10:00:00');

-- 3. Insert Blood Requests
INSERT INTO `blood_requests` (`request_id`, `unique_request_number`, `requester_id`, `patient_name`, `blood_group`, `units_required`, `hospital_name`, `hospital_address`, `state`, `city`, `pin_code`, `required_date`, `urgency`, `additional_information`, `contact_person`, `contact_phone`, `status`, `created_at`) VALUES
(1, 'LL-2026-000001', 2, 'Ramesh Kumar', 'O-', 2, 'Lilavati Hospital & Research Centre', 'A-791, Bandra Reclamation, Bandra West', 'Maharashtra', 'Mumbai', '400050', '2026-11-20', 'Emergency', 'Emergency open heart surgery scheduled. Urgent O negative blood required immediately.', 'Sarah Jenkins (Daughter)', '+91 9822334455', 'Donor Response Received', '2026-11-18 10:15:00'),
(2, 'LL-2026-000002', 3, 'Meena Sharma', 'A+', 3, 'Apollo Speciality Hospital', '21/2 Old Madras Road', 'Karnataka', 'Bengaluru', '560034', '2026-11-22', 'Urgent', 'Chemotherapy supportive platelet and whole blood transfusion.', 'Dr. Vivek Saxena', '+91 9811223344', 'Donors Notified', '2026-11-18 14:30:00'),
(3, 'LL-2026-000003', 2, 'Kiran Joshi', 'B+', 1, 'AIIMS Delhi', 'Ansari Nagar East', 'Delhi', 'New Delhi', '110029', '2026-11-25', 'Normal', 'Scheduled orthopedic replacement surgery.', 'Rajesh Joshi', '+91 9988776655', 'Pending', '2026-11-19 09:00:00'),
(4, 'LL-2026-000004', 3, 'Amitabh Roy', 'O+', 2, 'Fortis Hospital', 'Mulund Goregaon Link Rd', 'Maharashtra', 'Mumbai', '400078', '2026-11-15', 'Urgent', 'Accident trauma patient. Completed successfully.', 'Dr. Suresh', '+91 9811223344', 'Completed', '2026-11-14 08:00:00');

-- 4. Insert Request Responses
INSERT INTO `request_responses` (`response_id`, `request_id`, `donor_id`, `response_status`, `response_message`, `response_date`) VALUES
(1, 1, 1, 'Accepted', 'I am available and 15 mins away from Lilavati Hospital. Will arrive by 11:30 AM.', '2026-11-18 10:45:00'),
(2, 4, 2, 'Accepted', 'Donation completed at Fortis Hospital.', '2026-11-14 11:30:00');

-- 5. Insert Donation History
INSERT INTO `donations` (`donation_id`, `donor_id`, `request_id`, `donation_date`, `hospital_name`, `units_donated`, `verification_status`) VALUES
(1, 1, NULL, '2026-08-10', 'Tata Memorial Hospital, Mumbai', 1, 'Verified'),
(2, 2, 4, '2026-11-14', 'Fortis Hospital, Mumbai', 1, 'Verified'),
(3, 3, NULL, '2026-07-22', 'Manipal Hospital, Bengaluru', 1, 'Verified'),
(4, 4, NULL, '2026-06-15', 'AIIMS New Delhi', 1, 'Verified');

-- 6. Insert Notifications
INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `notification_type`, `related_request_id`, `is_read`, `created_at`) VALUES
(1, 4, 'CRITICAL: Emergency Blood Request', 'Emergency O- blood needed for patient Ramesh Kumar at Lilavati Hospital Mumbai.', 'emergency', 1, 1, '2026-11-18 10:16:00'),
(2, 2, 'Donor Accepted Your Request!', 'Dr. Marcus Vance (O-) accepted request LL-2026-000001: "I am available and 15 mins away".', 'response', 1, 1, '2026-11-18 10:46:00'),
(3, 6, 'Matching Blood Request', 'Urgent request for A+ blood at Apollo Speciality Hospital Bengaluru.', 'request', 2, 0, '2026-11-18 14:31:00'),
(4, 4, 'Welcome to LifeLink', 'Thank you for registering as a voluntary blood donor. Your profile is verified!', 'system', NULL, 1, '2026-11-01 10:00:00');

-- 7. Insert Favourites
INSERT INTO `favourites` (`favourite_id`, `user_id`, `donor_id`, `created_at`) VALUES
(1, 2, 1, '2026-11-18 11:00:00'),
(2, 3, 2, '2026-11-14 12:00:00');

-- 8. Insert Feedback
INSERT INTO `feedback` (`feedback_id`, `user_id`, `subject`, `rating`, `message`, `created_at`) VALUES
(1, 2, 'Life-saving platform', 5, 'We found a verified O- donor within 30 minutes for my father surgery. Truly blessed!', '2026-11-18 16:00:00'),
(2, 5, 'Great initiative for donors', 5, 'The availability toggle is super helpful. Clean and intuitive interface.', '2026-11-15 14:20:00');

-- 9. Insert Contact Messages
INSERT INTO `contact_messages` (`message_id`, `name`, `email`, `subject`, `message`, `is_read`, `created_at`) VALUES
(1, 'Dr. Rajesh Khanna', 'rkhanna@redcross.org', 'Blood Donation Camp Partnership', 'We are organizing a city-wide voluntary blood drive next month and would like to list it on LifeLink.', 0, '2026-11-17 11:45:00'),
(2, 'Anita Roy', 'anita.roy@gmail.com', 'Query regarding donor eligibility', 'Can a donor who recently recovered from minor surgery donate plasma or blood?', 1, '2026-11-16 15:20:00');

-- 10. Insert Admin Logs
INSERT INTO `admin_logs` (`log_id`, `admin_id`, `action`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'System Initialized', 'Database tables created and seed data populated successfully.', '127.0.0.1', '2026-11-19 10:00:00'),
(2, 1, 'User Verified', 'Verified donor profile for Dr. Marcus Vance (User #4).', '127.0.0.1', '2026-11-19 10:05:00');
