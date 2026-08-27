CREATE TABLE IF NOT EXISTS admins(
 id BIGSERIAL PRIMARY KEY, username VARCHAR(100) UNIQUE NOT NULL, email VARCHAR(255) UNIQUE NOT NULL,
 password_hash TEXT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS app_settings(
 id SMALLINT PRIMARY KEY DEFAULT 1 CHECK(id=1), app_name VARCHAR(150) NOT NULL DEFAULT 'OnPlus',
 app_logo TEXT, primary_color VARCHAR(20) DEFAULT '#6750A4', secondary_color VARCHAR(20) DEFAULT '#FFFFFF',
 background_color VARCHAR(20) DEFAULT '#101218', version VARCHAR(50), latest_version VARCHAR(50),
 update_required BOOLEAN NOT NULL DEFAULT FALSE, update_message TEXT, update_url TEXT, updated_at TIMESTAMPTZ DEFAULT NOW()
);
INSERT INTO app_settings(id) VALUES(1) ON CONFLICT(id) DO NOTHING;

CREATE TABLE IF NOT EXISTS sidebar_items(
 id BIGSERIAL PRIMARY KEY,title VARCHAR(150) NOT NULL,icon_url TEXT,icon_type VARCHAR(50) DEFAULT 'url',
 target_type VARCHAR(50) DEFAULT 'page',target_value TEXT,position INT DEFAULT 0,enabled BOOLEAN DEFAULT TRUE
);
CREATE TABLE IF NOT EXISTS categories(
 id BIGSERIAL PRIMARY KEY,external_id TEXT UNIQUE,name VARCHAR(200) NOT NULL,image_url TEXT,description TEXT,
 position INT DEFAULT 0,enabled BOOLEAN DEFAULT TRUE,source_url TEXT,raw_data JSONB DEFAULT '{}'::jsonb,imported_at TIMESTAMPTZ,created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS channels(
 id BIGSERIAL PRIMARY KEY,external_id TEXT UNIQUE,category_id BIGINT REFERENCES categories(id) ON DELETE CASCADE,
 name VARCHAR(200) NOT NULL,logo_url TEXT,stream_url TEXT,stream_type VARCHAR(80),description TEXT,
 enabled BOOLEAN DEFAULT TRUE,position INT DEFAULT 0,is_live BOOLEAN DEFAULT TRUE,source_url TEXT,
 raw_data JSONB DEFAULT '{}'::jsonb,imported_at TIMESTAMPTZ,created_at TIMESTAMPTZ DEFAULT NOW(),updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS matches(
 id BIGSERIAL PRIMARY KEY,external_id TEXT UNIQUE,home_team VARCHAR(200) NOT NULL,away_team VARCHAR(200) NOT NULL,
 home_logo TEXT,away_logo TEXT,competition VARCHAR(200),league_logo TEXT,match_date TIMESTAMPTZ,status VARCHAR(30) DEFAULT 'upcoming',
 score_home INT,score_away INT,stream_url TEXT,stream_type VARCHAR(80),source_url TEXT,raw_data JSONB DEFAULT '{}'::jsonb,imported_at TIMESTAMPTZ,
 created_at TIMESTAMPTZ DEFAULT NOW(),updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS notifications(
 id BIGSERIAL PRIMARY KEY,title VARCHAR(200) NOT NULL,message TEXT NOT NULL,image_url TEXT,type VARCHAR(50) DEFAULT 'info',
 action_type VARCHAR(50),action_value TEXT,enabled BOOLEAN DEFAULT TRUE,show_on_start BOOLEAN DEFAULT FALSE,
 start_date TIMESTAMPTZ,end_date TIMESTAMPTZ,created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS app_updates(
 id BIGSERIAL PRIMARY KEY,version VARCHAR(50) NOT NULL,version_code INT UNIQUE NOT NULL,
 update_type VARCHAR(20) NOT NULL DEFAULT 'optional' CHECK(update_type IN('optional','force')),
 title VARCHAR(200),message TEXT,download_url TEXT,enabled BOOLEAN DEFAULT TRUE,created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE TABLE IF NOT EXISTS home_sections(
 id BIGSERIAL PRIMARY KEY,title VARCHAR(150) NOT NULL,type VARCHAR(30) NOT NULL,
 image_url TEXT,position INT DEFAULT 0,enabled BOOLEAN DEFAULT TRUE,config JSONB DEFAULT '{}'::jsonb
);
CREATE INDEX IF NOT EXISTS idx_channels_category ON channels(category_id);
CREATE INDEX IF NOT EXISTS idx_matches_date ON matches(match_date);
