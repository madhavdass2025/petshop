<?php
session_start();

$db_file = 'pet_clinic.db';

try {
    $conn = new PDO("sqlite:$db_file");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$sql_queries = "
CREATE TABLE IF NOT EXISTS `patients` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL,
  `species` TEXT,
  `breed` TEXT,
  `age` INTEGER,
  `gender` TEXT,
  `owner_name` TEXT NOT NULL,
  `owner_contact` TEXT NOT NULL,
  `submitted_by` INTEGER,
  `cancel` INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS `consultations` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `patient_id` INTEGER NOT NULL,
  `consultation_date` TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
  `weight` REAL,
  `temperature` REAL,
  `chief_complaint` TEXT,
  `submitted_by` INTEGER,
  `cancel` INTEGER DEFAULT 0,
  FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `medicines_master` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `prescribed_medicines` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `consultation_id` INTEGER NOT NULL,
  `medicine_id` INTEGER NOT NULL,
  `frequency` TEXT,
  `dosage_form` TEXT,
  `unit_quantity` TEXT,
  `unit_type` TEXT,
  `food_relation` TEXT,
  `notes` TEXT,
  `submitted_by` INTEGER,
  `cancel` INTEGER DEFAULT 0,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`medicine_id`) REFERENCES `medicines_master` (`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `tests_master` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `name` TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `ordered_tests` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `consultation_id` INTEGER NOT NULL,
  `test_id` INTEGER NOT NULL,
  `result` TEXT,
  `submitted_by` INTEGER,
  `cancel` INTEGER DEFAULT 0,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`test_id`) REFERENCES `tests_master` (`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `attachments` (
  `id` INTEGER PRIMARY KEY AUTOINCREMENT,
  `consultation_id` INTEGER NOT NULL,
  `file_path` TEXT NOT NULL,
  `description` TEXT,
  `submitted_by` INTEGER,
  `cancel` INTEGER DEFAULT 0,
  FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `users` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `username` TEXT NOT NULL UNIQUE,
    `password` TEXT NOT NULL,
    `role` TEXT NOT NULL DEFAULT 'doctor'
);
";

try {
    $conn->exec($sql_queries);
} catch (PDOException $e) {
    die("Error creating tables: " . $e->getMessage());
}
?>
