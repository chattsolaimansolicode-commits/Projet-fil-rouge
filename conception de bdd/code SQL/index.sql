-- ============================================================
-- MLD – Gestion des PFE (Sans Admin)
-- ============================================================

-- ============================================================
-- TABLE USERS
-- ============================================================
CREATE TABLE USERS (
    id_user INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher') NOT NULL,
    filiere VARCHAR(100)
);

-- ============================================================
-- TABLE PROJECTS
-- ============================================================
CREATE TABLE PROJECTS (
    id_project INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    status ENUM(
        'proposed',
        'pending',
        'accepted',
        'rejected',
        'in_progress',
        'completed'
    ) NOT NULL DEFAULT 'proposed',

    id_teacher INT NOT NULL,
    id_student INT UNIQUE,

    CONSTRAINT fk_project_teacher
        FOREIGN KEY (id_teacher)
        REFERENCES USERS(id_user),

    CONSTRAINT fk_project_student
        FOREIGN KEY (id_student)
        REFERENCES USERS(id_user)
);

-- ============================================================
-- TABLE PROJECT_REQUESTS
-- ============================================================
CREATE TABLE PROJECT_REQUESTS (
    id_request INT AUTO_INCREMENT PRIMARY KEY,
    request_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    status ENUM(
        'pending',
        'accepted',
        'rejected'
    ) NOT NULL DEFAULT 'pending',

    id_user INT NOT NULL,
    id_project INT NOT NULL,

    UNIQUE KEY uq_student_project (id_user, id_project),

    CONSTRAINT fk_request_user
        FOREIGN KEY (id_user)
        REFERENCES USERS(id_user),

    CONSTRAINT fk_request_project
        FOREIGN KEY (id_project)
        REFERENCES PROJECTS(id_project)
);

-- ============================================================
-- TABLE DOCUMENTS
-- ============================================================
CREATE TABLE DOCUMENTS (
    id_document INT AUTO_INCREMENT PRIMARY KEY,

    file_name VARCHAR(200) NOT NULL,

    upload_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    status ENUM(
        'submitted',
        'validated',
        'rejected'
    ) NOT NULL DEFAULT 'submitted',

    teacher_comment TEXT,
    comment_date DATETIME NULL,

    id_project INT NOT NULL,

    CONSTRAINT fk_document_project
        FOREIGN KEY (id_project)
        REFERENCES PROJECTS(id_project)
);

-- ============================================================
-- TABLE PROJECT_TASKS
-- ============================================================
CREATE TABLE PROJECT_TASKS (
    id_task INT AUTO_INCREMENT PRIMARY KEY,

    task_name VARCHAR(150) NOT NULL,

    status ENUM(
        'not_started',
        'in_progress',
        'completed'
    ) NOT NULL DEFAULT 'not_started',

    deadline DATE,

    id_project INT NOT NULL,

    CONSTRAINT fk_task_project
        FOREIGN KEY (id_project)
        REFERENCES PROJECTS(id_project)
);

-- ============================================================
-- TABLE GRADES
-- ============================================================
CREATE TABLE GRADES (
    id_grade INT AUTO_INCREMENT PRIMARY KEY,

    note DECIMAL(5,2) NOT NULL,
    commentaire TEXT,

    id_project INT NOT NULL UNIQUE,
    id_teacher INT NOT NULL,

    CONSTRAINT fk_grade_project
        FOREIGN KEY (id_project)
        REFERENCES PROJECTS(id_project),

    CONSTRAINT fk_grade_teacher
        FOREIGN KEY (id_teacher)
        REFERENCES USERS(id_user)
);

-- ============================================================
-- TABLE DEFENSES
-- ============================================================
CREATE TABLE DEFENSES (
    id_defense INT AUTO_INCREMENT PRIMARY KEY,

    defense_date DATETIME NOT NULL,

    room VARCHAR(100),

    jury_members TEXT,

    status ENUM(
        'scheduled',
        'completed',
        'postponed',
        'cancelled'
    ) NOT NULL DEFAULT 'scheduled',

    id_project INT NOT NULL UNIQUE,

    CONSTRAINT fk_defense_project
        FOREIGN KEY (id_project)
        REFERENCES PROJECTS(id_project)
);