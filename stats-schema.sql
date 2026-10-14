CREATE TABLE IF NOT EXISTS public.poker_results (
    round_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL REFERENCES public."user"(id) ON DELETE CASCADE,
    room_code VARCHAR(8) NOT NULL,
    player_count INT NOT NULL CHECK (player_count BETWEEN 4 AND 20),
    rank INT NOT NULL CHECK (rank >= 1 AND rank <= player_count),
    points INT NOT NULL CHECK (points = player_count - rank),
    played_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (round_id, user_id),
    UNIQUE (round_id, rank)
);
CREATE TABLE IF NOT EXISTS public.poker_presence (
    room_code VARCHAR(8) NOT NULL REFERENCES public.poker_rooms(code) ON DELETE CASCADE,
    user_id INT NOT NULL REFERENCES public."user"(id) ON DELETE CASCADE,
    last_seen TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (room_code, user_id)
);
