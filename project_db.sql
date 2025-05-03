-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 03, 2025 at 07:14 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `project_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `DepartmentID` int(11) NOT NULL,
  `DepartmentName` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`DepartmentID`, `DepartmentName`) VALUES
(17, 'Accounting and Finance'),
(7, 'Aerospace Engineering'),
(20, 'Artificial Intelligence'),
(26, 'Aviation Management'),
(8, 'Avionics Engineering'),
(6, 'Bio Medical Engineering'),
(11, 'Business Administration (BBA)'),
(30, 'Business Analytics'),
(5, 'Computer Engineering'),
(18, 'Computer Games Design'),
(13, 'Computer Science'),
(1, 'Cyber Security'),
(21, 'Data Science'),
(25, 'Education'),
(2, 'Electrical Engineering (Power/Electronics/Telecom)'),
(15, 'English'),
(27, 'Health Care Management'),
(10, 'Information Security'),
(19, 'Information Technology'),
(31, 'International Relations (I.R)'),
(16, 'Management Sciences'),
(14, 'Mathematics'),
(32, 'MBBS'),
(4, 'Mechanical Engineering'),
(3, 'Mechatronics Engineering'),
(12, 'Physics'),
(29, 'Project Management'),
(24, 'Psychology'),
(22, 'Software Engineering'),
(23, 'Strategic Studies'),
(9, 'System Security'),
(28, 'Tourism and Hospitality Management');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `FeedbackID` int(11) NOT NULL,
  `ProjectID` int(11) DEFAULT NULL,
  `SenderID` int(11) DEFAULT NULL,
  `ReceiverID` int(11) DEFAULT NULL,
  `FeedbackText` text DEFAULT NULL,
  `FeedbackFilePath` varchar(255) DEFAULT NULL,
  `Rating` int(11) DEFAULT NULL,
  `IsRead` tinyint(1) DEFAULT 0,
  `SentAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `SubmissionID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`FeedbackID`, `ProjectID`, `SenderID`, `ReceiverID`, `FeedbackText`, `FeedbackFilePath`, `Rating`, `IsRead`, `SentAt`, `SubmissionID`) VALUES
(1, 1, 3, 2, 'please read this doc and implement it in mile stone 1', '../../uploads/3/mdm2behr.3qc20231205.pdf', NULL, 0, '2024-12-04 14:28:25', NULL),
(2, 1, 2, 3, 'ok sir i will read it and will update you', '', NULL, 0, '2024-12-04 14:29:09', NULL),
(3, 1, 2, 3, 'sir i want an appointment on friday can i have a time ', '', NULL, 0, '2024-12-04 14:29:26', NULL),
(4, 1, 3, 2, 'ok you can  came to my office at 11 am', '', NULL, 0, '2024-12-04 14:30:42', NULL),
(5, 1, 3, 2, 'test', '', NULL, 0, '2024-12-04 14:51:27', NULL),
(6, 1, 2, 3, 'hello\r\n', '', NULL, 0, '2025-05-01 05:03:35', NULL),
(7, 1, 2, 3, 'hello sir\r\n', '', NULL, 0, '2025-05-01 08:10:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `fileuploads`
--

CREATE TABLE `fileuploads` (
  `FileID` int(11) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `FileType` varchar(100) NOT NULL,
  `FileSize` int(11) NOT NULL,
  `UploadedBy` int(11) NOT NULL,
  `SubmissionID` int(11) DEFAULT NULL,
  `UploadedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fileuploads`
--

INSERT INTO `fileuploads` (`FileID`, `FileName`, `FilePath`, `FileType`, `FileSize`, `UploadedBy`, `SubmissionID`, `UploadedAt`) VALUES
(1, '1 (27).jpg', '../../uploads/submissions/2/3/681300cc8e9c0_1 (27).jpg', 'image/jpeg', 362698, 2, 3, '2025-05-01 05:04:12');

-- --------------------------------------------------------

--
-- Table structure for table `geminiconversations`
--

CREATE TABLE `geminiconversations` (
  `ConversationID` int(11) NOT NULL,
  `SupervisorID` int(11) NOT NULL,
  `Topic` varchar(255) DEFAULT NULL,
  `LastUpdated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `geminimessages`
--

CREATE TABLE `geminimessages` (
  `MessageID` int(11) NOT NULL,
  `ConversationID` int(11) NOT NULL,
  `IsUserMessage` tinyint(1) DEFAULT 1,
  `Content` text DEFAULT NULL,
  `SentAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `generatedprojects`
--

CREATE TABLE `generatedprojects` (
  `GeneratedProjectID` int(11) NOT NULL,
  `SupervisorID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Objectives` text DEFAULT NULL,
  `TechnologyStack` text DEFAULT NULL,
  `Complexity` varchar(50) DEFAULT NULL,
  `GeneratedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `IsAssigned` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `groupfeedback`
--

CREATE TABLE `groupfeedback` (
  `FeedbackID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `SenderID` int(11) NOT NULL,
  `FeedbackText` text NOT NULL,
  `FeedbackFilePath` varchar(255) DEFAULT NULL,
  `SentAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groupfeedback`
--

INSERT INTO `groupfeedback` (`FeedbackID`, `GroupID`, `SenderID`, `FeedbackText`, `FeedbackFilePath`, `SentAt`) VALUES
(1, 1, 3, 'impriove t', '', '2025-05-01 08:11:21');

-- --------------------------------------------------------

--
-- Table structure for table `groups`
--

CREATE TABLE `groups` (
  `GroupID` int(11) NOT NULL,
  `GroupName` varchar(255) NOT NULL,
  `SupervisorID` int(11) NOT NULL,
  `Description` text DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `groups`
--

INSERT INTO `groups` (`GroupID`, `GroupName`, `SupervisorID`, `Description`, `CreatedAt`, `Status`) VALUES
(1, 'art 1', 3, 'its mew one', '2025-04-24 12:06:54', 'Active'),
(2, 'tess', 3, '', '2025-04-24 13:10:46', 'Active');

--
-- Triggers `groups`
--
DELIMITER $$
CREATE TRIGGER `after_group_delete` AFTER DELETE ON `groups` FOR EACH ROW BEGIN
    UPDATE Users SET GroupCount = GroupCount - 1 WHERE UserID = OLD.SupervisorID;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_group_insert` AFTER INSERT ON `groups` FOR EACH ROW BEGIN
    UPDATE Users SET GroupCount = GroupCount + 1 WHERE UserID = NEW.SupervisorID;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `milestones`
--

CREATE TABLE `milestones` (
  `MilestoneID` int(11) NOT NULL,
  `ProjectID` int(11) DEFAULT NULL,
  `MilestoneTitle` varchar(50) DEFAULT NULL,
  `DueDate` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Description` text DEFAULT NULL,
  `MaxMarks` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `milestones`
--

INSERT INTO `milestones` (`MilestoneID`, `ProjectID`, `MilestoneTitle`, `DueDate`, `Status`, `CreatedAt`, `UpdatedAt`, `Description`, `MaxMarks`) VALUES
(1, 1, 'mile stone 1', '2024-12-07', 'Completed', '2024-12-04 14:27:29', '2024-12-04 14:29:40', 'create basic web layout', 0),
(2, 1, 'mile stone 2', '2024-12-01', 'Completed', '2024-12-04 14:31:34', '2024-12-04 14:31:53', 'test mile stone', 0),
(3, 2, 'asfdsafsaf', '2025-04-30', 'Completed', '2025-04-26 10:20:24', '2025-05-01 05:04:12', 'safdasczxfasdfsaf', 10);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `NotificationID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Message` text NOT NULL,
  `IsRead` tinyint(1) DEFAULT 0,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`NotificationID`, `UserID`, `Title`, `Message`, `IsRead`, `CreatedAt`) VALUES
(1, 2, 'New Group Feedback', 'Supervisor ali ahmad has posted new feedback for your group.', 0, '2025-05-01 08:11:21'),
(2, 2, 'Submission Accepted', 'Your submission for \'asfdsafsaf\' has been Accepted by ali ahmad.', 0, '2025-05-01 08:12:33');

-- --------------------------------------------------------

--
-- Table structure for table `pdfanalysis`
--

CREATE TABLE `pdfanalysis` (
  `AnalysisID` int(11) NOT NULL,
  `SupervisorID` int(11) NOT NULL,
  `FileName` varchar(255) NOT NULL,
  `FilePath` varchar(255) NOT NULL,
  `Summary` text DEFAULT NULL,
  `AnalyzedAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pdfanalysis`
--

INSERT INTO `pdfanalysis` (`AnalysisID`, `SupervisorID`, `FileName`, `FilePath`, `Summary`, `AnalyzedAt`) VALUES
(1, 3, 'lee25a.pdf', '../../uploads/supervisor_temp/680cba4f3e743_lee25a.pdf', 'This PDF document discusses research methodologies in computer science. \r\n    \r\nKey points:\r\n- Introduction to research methodologies\r\n- Quantitative vs qualitative approaches\r\n- Data collection techniques\r\n- Statistical analysis methods\r\n- Ethical considerations in research\r\n- Presenting research findings\r\n\r\nThe document provides a comprehensive overview of how to structure academic research, with particular emphasis on methods applicable to computer science and software engineering projects. It would be valuable for students beginning their dissertation work, especially in understanding how to design research questions and select appropriate methodologies.', '2025-04-26 10:49:51'),
(2, 3, 'final report 3.pdf', '../../uploads/supervisor_temp/680cba6bc7a65_final report 3.pdf', 'This PDF document discusses research methodologies in computer science. \r\n    \r\nKey points:\r\n- Introduction to research methodologies\r\n- Quantitative vs qualitative approaches\r\n- Data collection techniques\r\n- Statistical analysis methods\r\n- Ethical considerations in research\r\n- Presenting research findings\r\n\r\nThe document provides a comprehensive overview of how to structure academic research, with particular emphasis on methods applicable to computer science and software engineering projects. It would be valuable for students beginning their dissertation work, especially in understanding how to design research questions and select appropriate methodologies.', '2025-04-26 10:50:19'),
(3, 3, 'Mini Project 3 submission report .pdf', '../../uploads/supervisor_temp/680cbc13a637b_Mini Project 3 submission report .pdf', 'An error occurred while generating the summary. Please try again later: API Error: HTTP status code 404. Response: {\n  \"error\": {\n    \"code\": 404,\n    \"message\": \"models/gemini-pro is not found for API version v1beta, or is not supported for generateContent. Call ListModels to see the list of available models and their supported methods.\",\n    \"status\": \"NOT_FOUND\"\n  }\n}\n', '2025-04-26 10:57:24');

-- --------------------------------------------------------

--
-- Table structure for table `profile`
--

CREATE TABLE `profile` (
  `ProfileID` int(11) NOT NULL,
  `UserID` int(11) NOT NULL,
  `FirstName` varchar(100) DEFAULT NULL,
  `LastName` varchar(100) DEFAULT NULL,
  `ContactInfo` varchar(100) DEFAULT NULL,
  `DOB` date DEFAULT NULL,
  `CNIC` varchar(20) DEFAULT NULL,
  `ProfileImage` varchar(255) DEFAULT 'https://via.placeholder.com/250',
  `gender` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile`
--

INSERT INTO `profile` (`ProfileID`, `UserID`, `FirstName`, `LastName`, `ContactInfo`, `DOB`, `CNIC`, `ProfileImage`, `gender`) VALUES
(1, 3, 'ali', 'ahmad', '123456455', '2210-10-10', '1234564545', '6750665dad186.jpg', NULL),
(2, 2, 'usman', 'sadiq', '1234432312323', '1111-11-11', '11111111111', '675066933be8a.jpg', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `projecthistory`
--

CREATE TABLE `projecthistory` (
  `HistoryID` int(11) NOT NULL,
  `ProjectID` int(11) DEFAULT NULL,
  `Action` varchar(255) DEFAULT NULL,
  `ActionDate` timestamp NOT NULL DEFAULT current_timestamp(),
  `UserID` int(11) DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `DaysLate` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projecthistory`
--

INSERT INTO `projecthistory` (`HistoryID`, `ProjectID`, `Action`, `ActionDate`, `UserID`, `Status`, `DaysLate`) VALUES
(1, 1, 'Proposal Submitted', '2024-12-04 14:07:03', 2, NULL, NULL),
(2, 1, 'Accepted', '2024-12-04 14:26:50', 3, NULL, NULL),
(3, 1, 'Milestone Created: mile stone 1', '2024-12-04 14:27:29', 3, NULL, NULL),
(4, 1, 'Submitted Draft for mile stone 1', '2024-12-04 14:29:40', 2, 'On Time', 0),
(5, 1, 'Accept Submission', '2024-12-04 14:30:12', 3, 'Accepted', 0),
(6, 1, 'Milestone Created: mile stone 2', '2024-12-04 14:31:34', 3, NULL, NULL),
(7, 1, 'Submitted Draft for mile stone 2', '2024-12-04 14:31:53', 2, 'Late', 3),
(8, 2, 'Project Initiated', '2025-04-26 10:19:51', 3, NULL, NULL),
(9, 2, 'Milestone Created: asfdsafsaf', '2025-04-26 10:20:24', 3, NULL, NULL),
(10, 2, 'Submitted Proposal for asfdsafsaf', '2025-05-01 05:04:12', 2, 'Late', 1),
(11, 2, 'Submission Accepted', '2025-05-01 08:12:33', 3, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `ProjectID` int(11) NOT NULL,
  `Title` varchar(255) NOT NULL,
  `Description` text DEFAULT NULL,
  `Objectives` text DEFAULT NULL,
  `SupervisorID` int(11) DEFAULT NULL,
  `StudentID` int(11) DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `GroupID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`ProjectID`, `Title`, `Description`, `Objectives`, `SupervisorID`, `StudentID`, `Status`, `CreatedAt`, `UpdatedAt`, `GroupID`) VALUES
(1, 'Computer Vision', 'Role of AI in computer vision', 'salient object detection', 3, 2, 'Accepted', '2024-12-04 14:07:03', '2024-12-04 14:26:50', NULL),
(2, 'teset', 'sdfasfdasf', 'asfdsdfasfsaf', 3, NULL, 'Initiated', '2025-04-26 10:19:51', '2025-04-26 10:19:51', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `RoleID` int(11) NOT NULL,
  `RoleName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`RoleID`, `RoleName`) VALUES
(1, 'Admin'),
(3, 'Student'),
(2, 'Supervisor');

-- --------------------------------------------------------

--
-- Table structure for table `studentgroups`
--

CREATE TABLE `studentgroups` (
  `StudentGroupID` int(11) NOT NULL,
  `GroupID` int(11) NOT NULL,
  `StudentID` int(11) NOT NULL,
  `JoinedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `Status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `studentgroups`
--

INSERT INTO `studentgroups` (`StudentGroupID`, `GroupID`, `StudentID`, `JoinedAt`, `Status`) VALUES
(1, 1, 2, '2025-04-24 12:07:52', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `SubmissionID` int(11) NOT NULL,
  `ProjectID` int(11) DEFAULT NULL,
  `SubmissionType` varchar(50) DEFAULT NULL,
  `Version` int(11) DEFAULT 1,
  `FilePath` varchar(255) DEFAULT NULL,
  `SubmittedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `MilestoneID` int(11) DEFAULT NULL,
  `Remarks` text DEFAULT 'No remarks provided',
  `Status` varchar(50) DEFAULT NULL,
  `ReviewStatus` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
  `ReviewedBy` int(11) DEFAULT NULL,
  `ReviewedAt` timestamp NULL DEFAULT NULL,
  `ObtainedMarks` int(11) DEFAULT NULL,
  `Marks` decimal(5,2) DEFAULT NULL,
  `MaxMarks` decimal(5,2) DEFAULT 100.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`SubmissionID`, `ProjectID`, `SubmissionType`, `Version`, `FilePath`, `SubmittedAt`, `MilestoneID`, `Remarks`, `Status`, `ReviewStatus`, `ReviewedBy`, `ReviewedAt`, `ObtainedMarks`, `Marks`, `MaxMarks`) VALUES
(1, 1, 'Draft', 1, '../../uploads/student/2/6750675448703_mdm2behr.3qc20231205.pdf', '2024-12-04 14:29:40', 1, 'good keep it up', 'accept', 'Pending', NULL, NULL, NULL, NULL, 100.00),
(2, 1, 'Draft', 1, '../../uploads/student/2/675067d9784c1_mdm2behr.3qc20231205.pdf', '2024-12-04 14:31:53', 2, 'No remarks provided', NULL, 'Pending', NULL, NULL, NULL, NULL, 100.00),
(3, 2, 'Proposal', 1, NULL, '2025-05-01 05:04:12', 3, 'good job', 'Late', 'Accepted', 3, '2025-05-01 08:12:33', NULL, NULL, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `UserID` int(11) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `PasswordHash` varchar(255) NOT NULL,
  `StudentID` varchar(20) DEFAULT NULL,
  `DepartmentID` int(11) DEFAULT NULL,
  `RoleID` int(11) DEFAULT NULL,
  `StatusID` int(11) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `GroupCount` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`UserID`, `Username`, `Email`, `PasswordHash`, `StudentID`, `DepartmentID`, `RoleID`, `StatusID`, `CreatedAt`, `UpdatedAt`, `GroupCount`) VALUES
(1, 'admin@aiu.com', 'admin@aiu.com', '$2y$10$zCI/GXkKvwjEWmsjjzHmYeiP5.kDIXxYF64AefHkYUlF/4KhJ5vpa', NULL, NULL, 1, 5, '2024-12-04 12:25:39', '2024-12-04 12:25:39', 0),
(2, 'usman', 'usman@gmail.com', '$2y$10$YD1B3U95lVG3cLXhYsj3/eGME7lV47vEHooDS8uLAwMyPc9uvPjNS', '12345', 13, 3, 5, '2024-12-04 12:47:21', '2024-12-04 14:06:01', 0),
(3, 'ali', 'ali@gmail.com', '$2y$10$WGfUeup0BZhD9kZIxC5ACeKP0VMBFj0vLkPAUcjOQzkB0pgsDSJO6', '2222', 13, 2, 5, '2024-12-04 14:05:24', '2025-04-24 13:10:46', 2);

-- --------------------------------------------------------

--
-- Table structure for table `userstatus`
--

CREATE TABLE `userstatus` (
  `StatusID` int(11) NOT NULL,
  `StatusName` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `userstatus`
--

INSERT INTO `userstatus` (`StatusID`, `StatusName`) VALUES
(5, 'Active'),
(2, 'Approved'),
(4, 'Blocked'),
(1, 'Pending'),
(3, 'Rejected');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`DepartmentID`),
  ADD UNIQUE KEY `DepartmentName` (`DepartmentName`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`FeedbackID`),
  ADD KEY `ProjectID` (`ProjectID`),
  ADD KEY `SenderID` (`SenderID`),
  ADD KEY `ReceiverID` (`ReceiverID`),
  ADD KEY `SubmissionID` (`SubmissionID`);

--
-- Indexes for table `fileuploads`
--
ALTER TABLE `fileuploads`
  ADD PRIMARY KEY (`FileID`),
  ADD KEY `UploadedBy` (`UploadedBy`),
  ADD KEY `SubmissionID` (`SubmissionID`);

--
-- Indexes for table `geminiconversations`
--
ALTER TABLE `geminiconversations`
  ADD PRIMARY KEY (`ConversationID`),
  ADD KEY `SupervisorID` (`SupervisorID`);

--
-- Indexes for table `geminimessages`
--
ALTER TABLE `geminimessages`
  ADD PRIMARY KEY (`MessageID`),
  ADD KEY `ConversationID` (`ConversationID`);

--
-- Indexes for table `generatedprojects`
--
ALTER TABLE `generatedprojects`
  ADD PRIMARY KEY (`GeneratedProjectID`),
  ADD KEY `SupervisorID` (`SupervisorID`);

--
-- Indexes for table `groupfeedback`
--
ALTER TABLE `groupfeedback`
  ADD PRIMARY KEY (`FeedbackID`),
  ADD KEY `SenderID` (`SenderID`),
  ADD KEY `idx_groupfeedback_group` (`GroupID`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`GroupID`),
  ADD KEY `idx_groups_supervisor` (`SupervisorID`);

--
-- Indexes for table `milestones`
--
ALTER TABLE `milestones`
  ADD PRIMARY KEY (`MilestoneID`),
  ADD KEY `ProjectID` (`ProjectID`),
  ADD KEY `idx_milestones_maxmarks` (`MaxMarks`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`NotificationID`),
  ADD KEY `idx_notifications_user` (`UserID`);

--
-- Indexes for table `pdfanalysis`
--
ALTER TABLE `pdfanalysis`
  ADD PRIMARY KEY (`AnalysisID`),
  ADD KEY `SupervisorID` (`SupervisorID`);

--
-- Indexes for table `profile`
--
ALTER TABLE `profile`
  ADD PRIMARY KEY (`ProfileID`),
  ADD UNIQUE KEY `CNIC` (`CNIC`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `projecthistory`
--
ALTER TABLE `projecthistory`
  ADD PRIMARY KEY (`HistoryID`),
  ADD KEY `ProjectID` (`ProjectID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`ProjectID`),
  ADD KEY `SupervisorID` (`SupervisorID`),
  ADD KEY `StudentID` (`StudentID`),
  ADD KEY `idx_projects_group` (`GroupID`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`RoleID`),
  ADD UNIQUE KEY `RoleName` (`RoleName`);

--
-- Indexes for table `studentgroups`
--
ALTER TABLE `studentgroups`
  ADD PRIMARY KEY (`StudentGroupID`),
  ADD UNIQUE KEY `GroupID` (`GroupID`,`StudentID`),
  ADD KEY `idx_studentgroups_group` (`GroupID`),
  ADD KEY `idx_studentgroups_student` (`StudentID`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`SubmissionID`),
  ADD KEY `ProjectID` (`ProjectID`),
  ADD KEY `MilestoneID` (`MilestoneID`),
  ADD KEY `ReviewedBy` (`ReviewedBy`),
  ADD KEY `idx_submissions_review` (`ReviewStatus`),
  ADD KEY `idx_submissions_marks` (`Marks`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`UserID`),
  ADD UNIQUE KEY `Username` (`Username`),
  ADD UNIQUE KEY `Email` (`Email`),
  ADD UNIQUE KEY `StudentID` (`StudentID`),
  ADD KEY `DepartmentID` (`DepartmentID`),
  ADD KEY `RoleID` (`RoleID`),
  ADD KEY `StatusID` (`StatusID`);

--
-- Indexes for table `userstatus`
--
ALTER TABLE `userstatus`
  ADD PRIMARY KEY (`StatusID`),
  ADD UNIQUE KEY `StatusName` (`StatusName`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `DepartmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `FeedbackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `fileuploads`
--
ALTER TABLE `fileuploads`
  MODIFY `FileID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `geminiconversations`
--
ALTER TABLE `geminiconversations`
  MODIFY `ConversationID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `geminimessages`
--
ALTER TABLE `geminimessages`
  MODIFY `MessageID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generatedprojects`
--
ALTER TABLE `generatedprojects`
  MODIFY `GeneratedProjectID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groupfeedback`
--
ALTER TABLE `groupfeedback`
  MODIFY `FeedbackID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `GroupID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `milestones`
--
ALTER TABLE `milestones`
  MODIFY `MilestoneID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `NotificationID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pdfanalysis`
--
ALTER TABLE `pdfanalysis`
  MODIFY `AnalysisID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `profile`
--
ALTER TABLE `profile`
  MODIFY `ProfileID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `projecthistory`
--
ALTER TABLE `projecthistory`
  MODIFY `HistoryID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `ProjectID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `RoleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `studentgroups`
--
ALTER TABLE `studentgroups`
  MODIFY `StudentGroupID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `SubmissionID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `UserID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `userstatus`
--
ALTER TABLE `userstatus`
  MODIFY `StatusID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`ProjectID`) REFERENCES `projects` (`ProjectID`),
  ADD CONSTRAINT `feedback_ibfk_2` FOREIGN KEY (`SenderID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `feedback_ibfk_3` FOREIGN KEY (`ReceiverID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `feedback_ibfk_4` FOREIGN KEY (`SubmissionID`) REFERENCES `submissions` (`SubmissionID`);

--
-- Constraints for table `fileuploads`
--
ALTER TABLE `fileuploads`
  ADD CONSTRAINT `fileuploads_ibfk_1` FOREIGN KEY (`UploadedBy`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `fileuploads_ibfk_2` FOREIGN KEY (`SubmissionID`) REFERENCES `submissions` (`SubmissionID`) ON DELETE CASCADE;

--
-- Constraints for table `geminiconversations`
--
ALTER TABLE `geminiconversations`
  ADD CONSTRAINT `geminiconversations_ibfk_1` FOREIGN KEY (`SupervisorID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `geminimessages`
--
ALTER TABLE `geminimessages`
  ADD CONSTRAINT `geminimessages_ibfk_1` FOREIGN KEY (`ConversationID`) REFERENCES `geminiconversations` (`ConversationID`) ON DELETE CASCADE;

--
-- Constraints for table `generatedprojects`
--
ALTER TABLE `generatedprojects`
  ADD CONSTRAINT `generatedprojects_ibfk_1` FOREIGN KEY (`SupervisorID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `groupfeedback`
--
ALTER TABLE `groupfeedback`
  ADD CONSTRAINT `groupfeedback_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `groups` (`GroupID`) ON DELETE CASCADE,
  ADD CONSTRAINT `groupfeedback_ibfk_2` FOREIGN KEY (`SenderID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `groups_ibfk_1` FOREIGN KEY (`SupervisorID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `milestones`
--
ALTER TABLE `milestones`
  ADD CONSTRAINT `milestones_ibfk_1` FOREIGN KEY (`ProjectID`) REFERENCES `projects` (`ProjectID`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `pdfanalysis`
--
ALTER TABLE `pdfanalysis`
  ADD CONSTRAINT `pdfanalysis_ibfk_1` FOREIGN KEY (`SupervisorID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `profile`
--
ALTER TABLE `profile`
  ADD CONSTRAINT `profile_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`) ON DELETE CASCADE;

--
-- Constraints for table `projecthistory`
--
ALTER TABLE `projecthistory`
  ADD CONSTRAINT `projecthistory_ibfk_1` FOREIGN KEY (`ProjectID`) REFERENCES `projects` (`ProjectID`),
  ADD CONSTRAINT `projecthistory_ibfk_2` FOREIGN KEY (`UserID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`SupervisorID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `projects_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `users` (`UserID`),
  ADD CONSTRAINT `projects_ibfk_3` FOREIGN KEY (`GroupID`) REFERENCES `groups` (`GroupID`);

--
-- Constraints for table `studentgroups`
--
ALTER TABLE `studentgroups`
  ADD CONSTRAINT `studentgroups_ibfk_1` FOREIGN KEY (`GroupID`) REFERENCES `groups` (`GroupID`),
  ADD CONSTRAINT `studentgroups_ibfk_2` FOREIGN KEY (`StudentID`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`ProjectID`) REFERENCES `projects` (`ProjectID`),
  ADD CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`MilestoneID`) REFERENCES `milestones` (`MilestoneID`),
  ADD CONSTRAINT `submissions_ibfk_3` FOREIGN KEY (`ReviewedBy`) REFERENCES `users` (`UserID`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`DepartmentID`) REFERENCES `departments` (`DepartmentID`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`RoleID`) REFERENCES `roles` (`RoleID`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`StatusID`) REFERENCES `userstatus` (`StatusID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
