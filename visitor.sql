-- PostgreSQL

CREATE TABLE visitor (
    visitor_id SERIAL NOT NULL,
    hashids text,
    ip_address inet,
    hostname text,
    request_uri text,
    http_referer text,
    remote_port integer,
    user_agent text,
    visits_count integer DEFAULT 0 NOT NULL,
    last_visit timestamp without time zone DEFAULT now() NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    utm_source text,
    utm_medium text,
    utm_campaign text,
    utm_term text,
    utm_content text
);

COMMENT ON TABLE visitor IS 'Visitors.';



ALTER TABLE ONLY visitor
	ADD CONSTRAINT visitor_pkey PRIMARY KEY (visitor_id);

ALTER TABLE ONLY visitor
    ADD CONSTRAINT visitor_hashids_key UNIQUE (hashids);



CREATE FUNCTION visitor_visits_count() RETURNS trigger
	LANGUAGE plpgsql
AS $$	BEGIN
	IF OLD.last_visit < (NEW.last_visit - interval '24 hour') THEN
		UPDATE
			visitor
		SET
			visits_count = visits_count + 1
		WHERE
				visitor_id = NEW.visitor_id;

		RAISE NOTICE 'UPDATING visits count data for %, [%]' , NEW.visitor_id, NEW.last_visit;
	END IF;
	RETURN NULL; -- result is ignored since this is an AFTER trigger
END;
$$;



CREATE TRIGGER visitor_visits_count AFTER UPDATE OF last_visit ON visitor FOR EACH ROW EXECUTE PROCEDURE visitor_visits_count();
