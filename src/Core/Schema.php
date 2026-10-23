<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class Schema
{
    public static function migrate(PDO $pdo): void
    {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        if ($version < 1) {
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS settings (
                    key   TEXT PRIMARY KEY,
                    value TEXT
                );

                -- Um registo por exemplar na coleção Discogs (instance_id).
                CREATE TABLE IF NOT EXISTS releases (
                    instance_id   INTEGER PRIMARY KEY,
                    release_id    INTEGER NOT NULL,
                    master_id     INTEGER,
                    folder_id     INTEGER,
                    title         TEXT NOT NULL,
                    artist        TEXT NOT NULL,      -- nome de apresentação (com "feat.", "&", ...)
                    main_artist   TEXT NOT NULL,      -- 1.º artista, normalizado, usado no Last.fm
                    year          INTEGER,
                    format_name   TEXT,
                    formats       TEXT,
                    labels        TEXT,
                    catno         TEXT,
                    genres        TEXT NOT NULL DEFAULT '[]',
                    styles        TEXT NOT NULL DEFAULT '[]',
                    thumb         TEXT,
                    cover         TEXT,
                    rating        INTEGER NOT NULL DEFAULT 0,
                    date_added    TEXT,
                    sync_token    TEXT,
                    -- Last.fm: lf_status NULL = pendente | ok | notfound | error
                    lf_status        TEXT,
                    lf_name          TEXT,
                    lf_url           TEXT,
                    lf_listeners     INTEGER,
                    lf_playcount     INTEGER,
                    lf_userplaycount INTEGER,
                    lf_tags          TEXT,
                    lf_summary       TEXT,
                    lf_updated_at    TEXT
                );
                CREATE INDEX IF NOT EXISTS idx_releases_lf_status ON releases(lf_status);
                CREATE INDEX IF NOT EXISTS idx_releases_added ON releases(date_added);

                CREATE TABLE IF NOT EXISTS artists (
                    id               INTEGER PRIMARY KEY,  -- id do artista no Discogs
                    name             TEXT NOT NULL,
                    lf_status        TEXT,
                    lf_name          TEXT,
                    lf_url           TEXT,
                    lf_listeners     INTEGER,
                    lf_playcount     INTEGER,
                    lf_userplaycount INTEGER,
                    lf_tags          TEXT,
                    lf_summary       TEXT,
                    lf_updated_at    TEXT
                );

                CREATE TABLE IF NOT EXISTS release_artists (
                    instance_id INTEGER NOT NULL REFERENCES releases(instance_id) ON DELETE CASCADE,
                    artist_id   INTEGER NOT NULL REFERENCES artists(id) ON DELETE CASCADE,
                    position    INTEGER NOT NULL DEFAULT 0,
                    PRIMARY KEY (instance_id, artist_id)
                );
                CREATE INDEX IF NOT EXISTS idx_ra_artist ON release_artists(artist_id);
                SQL);
            $pdo->exec('PRAGMA user_version = 1');
        }

        if ($version < 2) {
            $pdo->exec(<<<'SQL'
                -- Alinhamento do disco (JSON normalizado a partir do Discogs), obtido a pedido.
                ALTER TABLE releases ADD COLUMN tracklist TEXT;
                ALTER TABLE releases ADD COLUMN tracklist_at TEXT;

                -- Ticketmaster: tm_status NULL = pendente | ok | notfound | error
                ALTER TABLE artists ADD COLUMN tm_status TEXT;
                ALTER TABLE artists ADD COLUMN tm_id TEXT;
                ALTER TABLE artists ADD COLUMN tm_checked_at TEXT;

                CREATE TABLE IF NOT EXISTS concerts (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    source        TEXT NOT NULL DEFAULT 'manual',  -- manual | ticketmaster
                    source_id     TEXT,
                    artist        TEXT NOT NULL,
                    artist_id     INTEGER REFERENCES artists(id) ON DELETE SET NULL,
                    title         TEXT,
                    lineup        TEXT,
                    date          TEXT NOT NULL,                   -- AAAA-MM-DD (hora local)
                    time          TEXT,                            -- HH:MM
                    venue         TEXT,
                    city          TEXT,
                    country       TEXT,
                    country_code  TEXT,
                    url           TEXT,
                    image         TEXT,
                    price         TEXT,
                    event_status  TEXT,                            -- onsale | offsale | postponed | rescheduled | cancelled
                    my_status     TEXT,                            -- interested | going | ticket
                    notes         TEXT,
                    edited        INTEGER NOT NULL DEFAULT 0,      -- editado à mão: a sincronização não lhe toca
                    hidden        INTEGER NOT NULL DEFAULT 0,      -- importado e apagado: não volta a aparecer
                    created_at    TEXT,
                    updated_at    TEXT,
                    UNIQUE (source, source_id)
                );
                CREATE INDEX IF NOT EXISTS idx_concerts_date ON concerts(date);
                CREATE INDEX IF NOT EXISTS idx_concerts_artist ON concerts(artist_id);
                SQL);
            $pdo->exec('PRAGMA user_version = 2');
        }

        if ($version < 3) {
            $pdo->exec(<<<'SQL'
                -- Vídeos YouTube do Discogs (JSON [{id, title}]), obtidos com o alinhamento.
                ALTER TABLE releases ADD COLUMN videos TEXT;
                -- Álbum correspondente no Deezer: NULL = por procurar · 0 = não encontrado.
                -- Os excertos não se guardam: os links expiram ao fim de algumas horas.
                ALTER TABLE releases ADD COLUMN deezer_id INTEGER;
                ALTER TABLE releases ADD COLUMN deezer_at TEXT;
                SQL);
            $pdo->exec('PRAGMA user_version = 3');
        }

        if ($version < 4) {
            // Género do concerto (BOL: "Jazz", "Fado"…; Ticketmaster: "Rock"…), para filtrar a agenda.
            $pdo->exec('ALTER TABLE concerts ADD COLUMN genre TEXT');
            $pdo->exec('PRAGMA user_version = 4');
        }
    }
}
