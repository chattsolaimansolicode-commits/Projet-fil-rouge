-- ============================================
-- PFE Manager - Database Schema
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================

CREATE DATABASE IF NOT EXISTS pfe_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pfe_manager;

-- ============================================
-- USERS TABLE (Students & Teachers)
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher') NOT NULL,
    department VARCHAR(100) DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    filiere VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- PROJECTS TABLE
-- ============================================
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    technologies VARCHAR(255),
    start_date DATE,
    end_date DATE,
    progress INT DEFAULT 0,
    status ENUM('pending', 'active', 'completed', 'rejected') DEFAULT 'pending',
    student_id INT NOT NULL,
    supervisor_id INT DEFAULT NULL,
    academic_year VARCHAR(20) DEFAULT NULL,
    filiere VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (supervisor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- TASKS TABLE
-- ============================================
CREATE TABLE tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started',
    deadline DATE DEFAULT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- ============================================
-- REQUESTS TABLE
-- ============================================
CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT DEFAULT NULL,
    type VARCHAR(100) NOT NULL,
    message TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    teacher_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- DOCUMENTS TABLE
-- ============================================
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    student_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(500) NOT NULL,
    filetype VARCHAR(50) DEFAULT NULL,
    filesize INT DEFAULT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    feedback TEXT DEFAULT NULL,
    feedback_by INT DEFAULT NULL,
    feedback_at DATETIME DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (feedback_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================
-- GRADES TABLE
-- ============================================
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    project_id INT NOT NULL,
    teacher_id INT NOT NULL,
    report_grade DECIMAL(5,2) DEFAULT NULL,
    presentation_grade DECIMAL(5,2) DEFAULT NULL,
    technical_grade DECIMAL(5,2) DEFAULT NULL,
    jury_grade DECIMAL(5,2) DEFAULT NULL,
    final_grade DECIMAL(5,2) DEFAULT NULL,
    mention VARCHAR(50) DEFAULT NULL,
    comments TEXT DEFAULT NULL,
    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- DEFENSES TABLE
-- ============================================
CREATE TABLE defenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    student_id INT NOT NULL,
    defense_date DATE NOT NULL,
    defense_time TIME NOT NULL,
    room VARCHAR(100),
    jury_members TEXT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    academic_year VARCHAR(20) DEFAULT NULL,
    filiere VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Password for all demo accounts: "password123" (bcrypt)
INSERT INTO users (name, email, password, role, department, filiere, academic_year) VALUES
('Solayman Ait Ali', 'solayman@student.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Informatique', 'Génie Logiciel', '2024-2025'),
('Yassine Benali', 'yassine@student.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'Informatique', 'Réseaux', '2024-2025'),
('Dr. Hamid Rachidi', 'hamid@teacher.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'Informatique', NULL, NULL),
('Dr. Sara Amrani', 'sara@teacher.ma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher', 'Informatique', NULL, NULL);

-- Sample project for Solayman
INSERT INTO projects (title, description, technologies, start_date, end_date, progress, status, student_id, supervisor_id, academic_year, filiere) VALUES
('PFE Manager - Système de Gestion des PFE', 'Application web pour la gestion des projets de fin d\'études universitaires, reliant étudiants et enseignants.', 'PHP, MySQL, Bootstrap, JavaScript', '2024-10-01', '2025-06-30', 65, 'active', 1, 3, '2024-2025', 'Génie Logiciel');

-- Default tasks for the project
INSERT INTO tasks (project_id, title, status, deadline, sort_order) VALUES
(1, 'Cahier des charges', 'completed', '2024-11-01', 1),
(1, 'Planification du projet', 'completed', '2024-11-15', 2),
(1, 'Conception de la base de données', 'completed', '2024-12-01', 3),
(1, 'Maquettes UI/UX', 'in_progress', '2025-01-15', 4),
(1, 'Codage Front-end', 'in_progress', '2025-03-01', 5),
(1, 'Codage Back-end', 'not_started', '2025-04-01', 6),
(1, 'Rapport', 'not_started', '2025-05-15', 7),
(1, 'Présentation', 'not_started', '2025-06-15', 8);

-- Sample request
INSERT INTO requests (student_id, teacher_id, type, message, status) VALUES
(1, 3, 'Changement de sujet', 'Je souhaite modifier légèrement le titre de mon projet pour mieux refléter son périmètre.', 'pending');
