CREATE TABLE IF NOT EXISTS public.poker_departures (
    room_code VARCHAR(8) NOT NULL,
    user_id INT NOT NULL REFERENCES public."user"(id) ON DELETE CASCADE,
    first_zero_round INT NOT NULL CHECK (first_zero_round > 0),
    stopped_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (room_code, user_id)
);
