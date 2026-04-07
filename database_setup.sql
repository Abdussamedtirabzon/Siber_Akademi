CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                password TEXT,
                role TEXT DEFAULT 'user',
                score INTEGER DEFAULT 0
            , email TEXT, reset_token TEXT, profile_pic TEXT);
CREATE TABLE sqlite_sequence(name,seq);
CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                order_index INTEGER DEFAULT 0
            );
CREATE TABLE lessons (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                title TEXT,
                content TEXT,
                image_url TEXT,
                order_index INTEGER DEFAULT 0,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );
CREATE TABLE questions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                lesson_id INTEGER,
                type TEXT, -- 'quiz' veya 'terminal'
                question_text TEXT,
                correct_answer TEXT,
                options TEXT, -- JSON formatında şıklar
                FOREIGN KEY (lesson_id) REFERENCES lessons(id)
            );
CREATE TABLE user_progress (
                user_id INTEGER,
                lesson_id INTEGER,
                is_completed INTEGER DEFAULT 0,
                PRIMARY KEY (user_id, lesson_id)
            );
