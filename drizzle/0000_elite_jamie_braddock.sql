CREATE TABLE `bookings` (
	`id` text PRIMARY KEY NOT NULL,
	`date` text NOT NULL,
	`start` integer NOT NULL,
	`end` integer NOT NULL,
	`table_id` integer NOT NULL,
	`guests` integer NOT NULL,
	`first_name` text NOT NULL,
	`last_name` text NOT NULL,
	`phone` text NOT NULL,
	`birth_day` integer NOT NULL,
	`birth_month` integer NOT NULL,
	`consent` integer DEFAULT 0 NOT NULL,
	`consent_at` text,
	`items` text NOT NULL,
	`notes` text DEFAULT '' NOT NULL,
	`status` text DEFAULT 'confirmed' NOT NULL,
	`created_at` text NOT NULL
);
--> statement-breakpoint
CREATE INDEX `bookings_date` ON `bookings` (`date`);--> statement-breakpoint
CREATE INDEX `bookings_phone` ON `bookings` (`phone`);--> statement-breakpoint
CREATE TABLE `menu_items` (
	`id` text PRIMARY KEY NOT NULL,
	`name` text NOT NULL,
	`category` text NOT NULL,
	`price` integer NOT NULL,
	`active` integer DEFAULT 1 NOT NULL
);
--> statement-breakpoint
CREATE TABLE `booking_slots` (
	`id` text PRIMARY KEY NOT NULL,
	`booking_id` text NOT NULL,
	`table_id` integer NOT NULL,
	`date` text NOT NULL,
	`minute` integer NOT NULL,
	FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON UPDATE no action ON DELETE cascade
);
--> statement-breakpoint
CREATE UNIQUE INDEX `unique_table_slot` ON `booking_slots` (`table_id`,`date`,`minute`);--> statement-breakpoint
CREATE TABLE `dining_tables` (
	`id` integer PRIMARY KEY NOT NULL,
	`name` text NOT NULL,
	`capacity` integer NOT NULL,
	`x` integer NOT NULL,
	`y` integer NOT NULL,
	`active` integer DEFAULT 1 NOT NULL
);
