--
-- PostgreSQL database dump
--

-- Dumped from database version 15.3 (Debian 15.3-1.pgdg110+1)
-- Dumped by pg_dump version 15.3 (Homebrew)

-- Started on 2023-06-13 21:26:10 CEST

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- TOC entry 6 (class 2615 OID 16390)
-- Name: progetto_esame; Type: SCHEMA; Schema: -; Owner: progetto
--

CREATE SCHEMA progetto_esame;


ALTER SCHEMA progetto_esame OWNER TO progetto;

--
-- TOC entry 889 (class 1247 OID 16511)
-- Name: anno_insegnamento; Type: DOMAIN; Schema: progetto_esame; Owner: progetto
--

CREATE DOMAIN progetto_esame.anno_insegnamento AS integer
	CONSTRAINT anno_insegnamento_check CHECK (((VALUE > 0) AND (VALUE < 4)));


ALTER DOMAIN progetto_esame.anno_insegnamento OWNER TO progetto;

--
-- TOC entry 882 (class 1247 OID 16404)
-- Name: tipo_corso_laurea; Type: DOMAIN; Schema: progetto_esame; Owner: progetto
--

CREATE DOMAIN progetto_esame.tipo_corso_laurea AS character(1)
	CONSTRAINT tipo_corso_laurea_check CHECK ((VALUE = ANY (ARRAY['T'::bpchar, 'M'::bpchar])));


ALTER DOMAIN progetto_esame.tipo_corso_laurea OWNER TO progetto;

--
-- TOC entry 902 (class 1247 OID 16622)
-- Name: valutazione_esame; Type: DOMAIN; Schema: progetto_esame; Owner: progetto
--

CREATE DOMAIN progetto_esame.valutazione_esame AS integer
	CONSTRAINT valutazione_esame_check CHECK (((VALUE >= 0) AND (VALUE <= 30)));


ALTER DOMAIN progetto_esame.valutazione_esame OWNER TO progetto;

--
-- TOC entry 252 (class 1255 OID 16705)
-- Name: controllo_anno_insegnamento(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.controllo_anno_insegnamento() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare 
tipo_cdl corsi_laurea.tipo%type;
begin
	
	select tipo into tipo_cdl
	from corsi_laurea cl 
	where cl.codice = new.corso_laurea;

	if tipo_cdl = 'M' and new.anno = 3 then 
		raise exception 'Un corso indicato come di tipo magistrale non può avere insegnamenti di anni superiori al secondo.';
	else
		return new;
	end if;
	
end;
$$;


ALTER FUNCTION progetto_esame.controllo_anno_insegnamento() OWNER TO progetto;

--
-- TOC entry 262 (class 1255 OID 16822)
-- Name: controllo_numero_insegnamenti_docente(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.controllo_numero_insegnamenti_docente() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare 
conto_insegnamenti integer;
begin
	
	-- escludo il caso in cui si sta aggiornando un corso mantenendo il medesimo docente
	-- l'update verrebbe bloccato perchè il docente risulterebbe con già 3 corsi
	-- ma tale operazione non aumenta il numero di corsi del docente
	if TG_OP = 'UPDATE' and new.docente = old.docente then 
		return new;
	end if;
	
	-- nei casi in cui si sta effettuando l'insert
	-- o l'update del docente che tiene il corso
	-- ovvero il docente ha un incremento nel numero di corsi a lui assegnati
	-- conto i corsi e basta questo
	conto_insegnamenti := (
		select count(*)
		from insegnamenti i
		where i.docente = new.docente 
	);
	
	if conto_insegnamenti >= 3 then 
		raise exception 'Il docente % gestisce già il numero massimo di corsi.', new.docente;
	else 
		return new;
	end if;

end;
$$;


ALTER FUNCTION progetto_esame.controllo_numero_insegnamenti_docente() OWNER TO progetto;

--
-- TOC entry 243 (class 1255 OID 16713)
-- Name: controllo_propedeuticità_iscrizione_appello(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame."controllo_propedeuticità_iscrizione_appello"() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
p "propedeuticità"%rowtype;
e "esami"%rowtype;
begin
	
	for p in (
		-- ottiene tutte le propedeuticità dell'insegnamento al cui appello lo studente sta cercando di iscriversi
		select p_table.* from propedeuticità p_table
		inner join appelli a on a.corso_laurea = p_table.codice_cdl_insegnamento_propedeuticità and a.insegnamento = p_table.codice_insegnamento_propedeuticità
		where a.id = new.appello
	)
	loop

		-- ottiene l'esame più recente sostenuto dallo studente che sta tentando l'iscrizione all'appello
		-- relativo al corso propedutico corrente
		select e_table.* into e
		from esami e_table
		inner join appelli a on e_table.appello = a.id
		where a.corso_laurea = p.codice_cdl_insegnamento_propedeutico and a.insegnamento = p.codice_insegnamento_propedeutico
			and e_table.studente = new.studente
		order by a."data" desc
	 	limit 1;
	
		if not found or e.valutazione is null or e.valutazione < 18 then
			raise exception 'Impossibile iscriversi all''appello in quanto non hai ancora soddisfatto tutte le propedeuticità.';
		end if;
			
	end loop;

	return new;

end;
$$;


ALTER FUNCTION progetto_esame."controllo_propedeuticità_iscrizione_appello"() OWNER TO progetto;

--
-- TOC entry 263 (class 1255 OID 16826)
-- Name: delete_docente(character varying, character varying[], character varying[], character varying[]); Type: PROCEDURE; Schema: progetto_esame; Owner: progetto
--

CREATE PROCEDURE progetto_esame.delete_docente(IN doc character varying, IN cl_arr character varying[], IN ins_arr character varying[], IN doc_arr character varying[])
    LANGUAGE plpgsql
    AS $$
declare 
num_ins integer;
ins_el varchar(15);
doc_el varchar(15);
begin 

	-- controllo parametri
	
	if(array_length(ins_arr, 1) <> array_length(doc_arr, 1) or array_length(ins_arr, 1) <> array_length(cl_arr, 1)) then
		raise exception 'Il numero di insegnamenti non corrisponde al numero di docenti.';
	end if;
	
	num_ins := (select count(*) from insegnamenti where docente = doc);

	if(array_length(ins_arr, 1) <> num_ins) then
		raise exception 'Il docente ha un numero di insegnamenti superiore a quello fornito (totali: %, forniti: %).', num_ins, array_length(ins_arr, 1);
	end if;

	-- aggiornamento insegnamenti
	for x in 1..array_length(ins_arr, 1) loop
		update insegnamenti set docente = doc_arr[x]
			where codice = ins_arr[x] and corso_laurea = cl_arr[x] and docente = doc;
		if not found then
			raise exception 'L''insegnamento % del corso di laurea % non appartiene al docente %.', ins_arr[x], cl_arr[x], doc;
		end if;
   	end loop;
   
   -- rimozione docente
   delete from docenti where email = doc;
	
end;
$$;


ALTER PROCEDURE progetto_esame.delete_docente(IN doc character varying, IN cl_arr character varying[], IN ins_arr character varying[], IN doc_arr character varying[]) OWNER TO progetto;

--
-- TOC entry 260 (class 1255 OID 16736)
-- Name: get_appelli_studente_non_iscritto_futuri(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.get_appelli_studente_non_iscritto_futuri(s integer) RETURNS TABLE(appello integer, data_appello date, cdl character varying, codice_insegnamento character varying, nome_insegnamento character varying, descrizione_insegnamento text, anno_insegnamento progetto_esame.anno_insegnamento)
    LANGUAGE plpgsql
    AS $$
declare
c studenti.corso_laurea%type;
begin
	
	select std.corso_laurea into c from studenti std where std.matricola = s;
	
	return query ((
		select a.id, a.data, a.corso_laurea, a.insegnamento, i.nome, i.descrizione, i.anno
	    from appelli a
	    inner join insegnamenti i on a.corso_laurea = i.corso_laurea and a.insegnamento = i.codice
	    where a.corso_laurea = c and a.data > now()
	    order by a.insegnamento, a.data asc)
	    except
	    (select a.id, a.data, a.corso_laurea, a.insegnamento, i.nome, i.descrizione, i.anno distinct
	    from appelli a
	    inner join insegnamenti i on a.corso_laurea = i.corso_laurea and a.insegnamento = i.codice
	    inner join esami e on e.appello = a.id
	    where e.studente = s)
	);

end;
$$;


ALTER FUNCTION progetto_esame.get_appelli_studente_non_iscritto_futuri(s integer) OWNER TO progetto;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 218 (class 1259 OID 16513)
-- Name: insegnamenti; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.insegnamenti (
    codice character varying(15) NOT NULL,
    corso_laurea character varying(15) NOT NULL,
    nome character varying(128) NOT NULL,
    descrizione text NOT NULL,
    anno progetto_esame.anno_insegnamento NOT NULL,
    docente character varying(254) NOT NULL
);


ALTER TABLE progetto_esame.insegnamenti OWNER TO progetto;

--
-- TOC entry 250 (class 1255 OID 16749)
-- Name: get_insegnamenti_a_cui_propedeutico(character varying, character varying); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.get_insegnamenti_a_cui_propedeutico(cl character varying, ci character varying) RETURNS SETOF progetto_esame.insegnamenti
    LANGUAGE plpgsql
    AS $$
begin
	
	return query (
		select i.*
  		from propedeuticità p
  		inner join insegnamenti i on i.codice = p.codice_insegnamento_propedeuticità and i.corso_laurea = p.codice_cdl_insegnamento_propedeuticità
  		where p.codice_insegnamento_propedeutico = ci and p.codice_cdl_insegnamento_propedeutico = cl
	);

end;
$$;


ALTER FUNCTION progetto_esame.get_insegnamenti_a_cui_propedeutico(cl character varying, ci character varying) OWNER TO progetto;

--
-- TOC entry 255 (class 1255 OID 16747)
-- Name: get_insegnamenti_aggiungibili_come_propedeutici(character varying, character varying); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.get_insegnamenti_aggiungibili_come_propedeutici(cl character varying, ci character varying) RETURNS SETOF progetto_esame.insegnamenti
    LANGUAGE plpgsql
    AS $$
begin
	
	return query (
		-- tutti gli insegnamenti nel medesimo corso di laurea del corso fornito ad eccezione di quest'ultimo
		(select i.*
		from insegnamenti i 
		where i.corso_laurea = cl and i.codice <> ci)
		except -- (esclude duplicati)
		-- corsi che sono già segnati come propedeutici per il corso fornito
		(select i.*
		from insegnamenti i
		inner join propedeuticità p on p.codice_insegnamento_propedeutico = i.codice and p.codice_cdl_insegnamento_propedeutico = i.corso_laurea
		where p.codice_cdl_insegnamento_propedeuticità = cl and p.codice_insegnamento_propedeuticità = ci)
		except -- (esclude propedeuticità circolari)
		-- corsi a cui il corso fornito è propedeutico
		(select i.*
		from insegnamenti i
		inner join propedeuticità p on p.codice_insegnamento_propedeuticità = i.codice and p.codice_cdl_insegnamento_propedeuticità = i.corso_laurea
		where p.codice_cdl_insegnamento_propedeutico = cl and p.codice_insegnamento_propedeutico = ci)
	);

end;
$$;


ALTER FUNCTION progetto_esame.get_insegnamenti_aggiungibili_come_propedeutici(cl character varying, ci character varying) OWNER TO progetto;

--
-- TOC entry 251 (class 1255 OID 16748)
-- Name: get_insegnamenti_propedeutici(character varying, character varying); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.get_insegnamenti_propedeutici(cl character varying, ci character varying) RETURNS SETOF progetto_esame.insegnamenti
    LANGUAGE plpgsql
    AS $$
begin
	
	return query (
	
	select i.*
    from propedeuticità p
    inner join insegnamenti i on i.codice = p.codice_insegnamento_propedeutico and i.corso_laurea = p.codice_cdl_insegnamento_propedeutico
    where p.codice_insegnamento_propedeuticità = ci and p.codice_cdl_insegnamento_propedeuticità = cl
	
	);

end;
$$;


ALTER FUNCTION progetto_esame.get_insegnamenti_propedeutici(cl character varying, ci character varying) OWNER TO progetto;

--
-- TOC entry 249 (class 1255 OID 16732)
-- Name: get_iscrizioni_appello(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.get_iscrizioni_appello(id_appello integer) RETURNS TABLE(matricola_studente integer, email_studente character varying, nome_studente character varying, cognome_studente character varying, valutazione_esame progetto_esame.valutazione_esame)
    LANGUAGE plpgsql
    AS $$
begin

	return query(
		select s.matricola, s.email, s.nome, s.cognome, e.valutazione
		from esami e 
		inner join studenti s on e.studente = s.matricola
		where e.appello = id_appello
	);
	

end;
$$;


ALTER FUNCTION progetto_esame.get_iscrizioni_appello(id_appello integer) OWNER TO progetto;

--
-- TOC entry 256 (class 1255 OID 16733)
-- Name: get_iscrizioni_attive_appelli_studente(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.get_iscrizioni_attive_appelli_studente(s integer) RETURNS TABLE(appello integer, data_appello date, valutazione progetto_esame.valutazione_esame, cdl character varying, codice_insegnamento character varying, nome_insegnamento character varying, descrizione_insegnamento text, anno_insegnamento progetto_esame.anno_insegnamento, email_docente character varying, nome_docente character varying, cognome_docente character varying)
    LANGUAGE plpgsql
    AS $$
begin
	
	return query (
		select a.id, a."data", e.valutazione, i.corso_laurea, i.codice, i.nome, i.descrizione, i.anno, i.docente,d.nome, d.cognome
		from appelli a
		inner join esami e on a.id = e.appello 
		inner join insegnamenti i on i.corso_laurea = a.corso_laurea and i.codice = a.insegnamento
		inner join docenti d on d.email = i.docente
		where e.studente = s and (a.data >= NOW() and e.valutazione is null)
		order by a."data"
	);

end;
$$;


ALTER FUNCTION progetto_esame.get_iscrizioni_attive_appelli_studente(s integer) OWNER TO progetto;

--
-- TOC entry 253 (class 1255 OID 16707)
-- Name: previeni_appelli_per_insegnamenti_stesso_anno(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.previeni_appelli_per_insegnamenti_stesso_anno() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare 
conteggio_appello_stesso_anno_stessa_data integer;
anno_corso insegnamenti.anno%type;
begin
	
	select anno into anno_corso from insegnamenti i where i.codice = new.insegnamento and i.corso_laurea = new.corso_laurea; 
	
	conteggio_appello_stesso_anno_stessa_data := (
		select count(*)
		from appelli a 
		inner join insegnamenti i on i.codice = a.insegnamento and i.corso_laurea = a.corso_laurea 
		where new."data" = a."data" and i.anno = anno_corso and a.corso_laurea = new.corso_laurea
	);
	
	if conteggio_appello_stesso_anno_stessa_data >= 1 then 
		raise exception 'Esiste già un appello dello stesso anno nello stesso corso di laurea in data %.', to_char(new.data, 'DD/MM/YYYY');
	else 
		return new;
	end if;

end;
$$;


ALTER FUNCTION progetto_esame.previeni_appelli_per_insegnamenti_stesso_anno() OWNER TO progetto;

--
-- TOC entry 259 (class 1255 OID 16820)
-- Name: previeni_eliminazione_appello_passato(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.previeni_eliminazione_appello_passato() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
da appelli%rowtype; -- delete_appello
ni integer; -- numero_iscritti
begin 

	select * into da
	from appelli a
	where a.id = old.id;

	select count(e.studente) into ni
	from esami e
	where e.appello = old.id;

	if(da.data <= now() and ni > 0) then
		raise exception 'Impossibile eliminare un appello già avvenuto con studenti che lo hanno sostenuto.';
	end if;

	return old;
	
end;
$$;


ALTER FUNCTION progetto_esame.previeni_eliminazione_appello_passato() OWNER TO progetto;

--
-- TOC entry 230 (class 1255 OID 16687)
-- Name: previeni_inserimento_propedeuticità_cdl_differenti(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame."previeni_inserimento_propedeuticità_cdl_differenti"() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
	if new.codice_cdl_insegnamento_propedeutico <> new.codice_cdl_insegnamento_propedeuticità then 
		raise exception 'Impossibile inserire propedeuticità tra insegnamenti di corsi di laurea differenti.';
	else
		return new;
	end if;
end;
$$;


ALTER FUNCTION progetto_esame."previeni_inserimento_propedeuticità_cdl_differenti"() OWNER TO progetto;

--
-- TOC entry 254 (class 1255 OID 16695)
-- Name: previeni_inserimento_propedeuticità_medesimo_insegnamento(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame."previeni_inserimento_propedeuticità_medesimo_insegnamento"() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
begin
	if new.codice_cdl_insegnamento_propedeutico = new.codice_cdl_insegnamento_propedeuticità and new.codice_insegnamento_propedeutico = new.codice_insegnamento_propedeuticità then 
		raise exception 'Impossibile inserire la propedeuticità di un corso per se stesso.';
	else
		return new;
	end if;
end;
$$;


ALTER FUNCTION progetto_esame."previeni_inserimento_propedeuticità_medesimo_insegnamento"() OWNER TO progetto;

--
-- TOC entry 258 (class 1255 OID 16806)
-- Name: previeni_inserimento_propedeuticità_transitive_loop(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame."previeni_inserimento_propedeuticità_transitive_loop"() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
tp propedeuticità%rowtype;
begin

	with recursive get_propedeuticità_transitive as (
		select *
		from propedeuticità
		where codice_insegnamento_propedeuticità = new.codice_insegnamento_propedeutico 
			and codice_cdl_insegnamento_propedeuticità = new.codice_cdl_insegnamento_propedeutico
	
		union
		
		select p.*
		from propedeuticità p 
		inner join get_propedeuticità_transitive gpt on gpt.codice_insegnamento_propedeutico = p.codice_insegnamento_propedeuticità
			and gpt.codice_cdl_insegnamento_propedeutico = p.codice_cdl_insegnamento_propedeuticità
	) 
	select *
	from get_propedeuticità_transitive
	where codice_insegnamento_propedeutico = new.codice_insegnamento_propedeuticità
		and codice_cdl_insegnamento_propedeutico = new.codice_cdl_insegnamento_propedeuticità
	into tp;

	if found then
		raise exception 'Impossibile inserire propedeuticità in quanto genera un loop di propedeuticità per transitività.';
	end if;

	return new;

end;
$$;


ALTER FUNCTION progetto_esame."previeni_inserimento_propedeuticità_transitive_loop"() OWNER TO progetto;

--
-- TOC entry 231 (class 1255 OID 16756)
-- Name: previeni_iscrizione_appello_data_passata(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.previeni_iscrizione_appello_data_passata() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare 
da appelli."data"%type;
begin

	select a."data" into da from appelli a where new.appello = a.id;
	
	if(da < now()) then
		raise exception 'Impossibile iscriversi ad un appello passato.';
	end if;

	return new;

end;
$$;


ALTER FUNCTION progetto_esame.previeni_iscrizione_appello_data_passata() OWNER TO progetto;

--
-- TOC entry 244 (class 1255 OID 16758)
-- Name: previeni_iscrizione_appello_insegnamento_non_in_cdl(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.previeni_iscrizione_appello_insegnamento_non_in_cdl() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare 
cdl_i insegnamenti.corso_laurea%type;
cdl_s insegnamenti.corso_laurea%type;
begin

	select i.corso_laurea into cdl_i
	from insegnamenti i
	inner join appelli a on a.corso_laurea = i.corso_laurea and a.insegnamento = i.codice
	where a.id = new.appello;

	select s.corso_laurea into cdl_s
	from studenti s 
	where s.matricola = new.studente;
	
	if(cdl_i <> cdl_s) then
		raise exception 'Impossibile iscriversi ad un appello di un insegnamento di un altro corso di laurea.';
	end if;

	return new;

end;
$$;


ALTER FUNCTION progetto_esame.previeni_iscrizione_appello_insegnamento_non_in_cdl() OWNER TO progetto;

--
-- TOC entry 264 (class 1255 OID 16852)
-- Name: previeni_modifica_tipo_corso_laurea_insegnamenti_discordi(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.previeni_modifica_tipo_corso_laurea_insegnamenti_discordi() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
x integer;
begin 
	
	if old.tipo <> new.tipo and new.tipo = 'M' then 
		x := (select count(*) from insegnamenti where corso_laurea = old.codice and anno = 3);
		if x > 0 then
			raise exception 'I corsi magistrali non possono avere insegnamenti segnati come del 3° anno.';
		else
			return new;
		end if;
	else 
		return new;
	end if;
	
end;
$$;


ALTER FUNCTION progetto_esame.previeni_modifica_tipo_corso_laurea_insegnamenti_discordi() OWNER TO progetto;

--
-- TOC entry 248 (class 1255 OID 16722)
-- Name: produci_carriera_completa_studente(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.produci_carriera_completa_studente(s integer) RETURNS TABLE(appello integer, data_appello date, valutazione progetto_esame.valutazione_esame, cdl character varying, codice_insegnamento character varying, nome_insegnamento character varying, descrizione_insegnamento text, anno_insegnamento progetto_esame.anno_insegnamento, email_docente character varying, nome_docente character varying, cognome_docente character varying)
    LANGUAGE plpgsql
    AS $$
begin
	
	return query (
		select e.appello, a."data", e.valutazione, i.corso_laurea, i.codice, i.nome, i.descrizione, i.anno, i.docente,d.nome, d.cognome
		from esami e
		inner join appelli a on a.id = e.appello 
		inner join insegnamenti i on i.corso_laurea = a.corso_laurea and i.codice = a.insegnamento
		inner join docenti d on d.email = i.docente
		where e.studente = s and (a.data <= now() or e.valutazione is not null)
		order by a."data" desc
	);

end;
$$;


ALTER FUNCTION progetto_esame.produci_carriera_completa_studente(s integer) OWNER TO progetto;

--
-- TOC entry 246 (class 1255 OID 16803)
-- Name: produci_carriera_completa_studente_storico(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.produci_carriera_completa_studente_storico(s integer) RETURNS TABLE(appello integer, data_appello date, valutazione progetto_esame.valutazione_esame, cdl character varying, codice_insegnamento character varying, nome_insegnamento character varying, descrizione_insegnamento text, anno_insegnamento progetto_esame.anno_insegnamento, email_docente character varying, nome_docente character varying, cognome_docente character varying)
    LANGUAGE plpgsql
    AS $$
begin
	
	return query (
		select e.appello, a."data", e.valutazione, i.corso_laurea, i.codice, i.nome, i.descrizione, i.anno, i.docente,d.nome, d.cognome
		from storico_esami e
		inner join appelli a on a.id = e.appello 
		inner join insegnamenti i on i.corso_laurea = a.corso_laurea and i.codice = a.insegnamento
		inner join docenti d on d.email = i.docente
		where e.studente = s and (a.data <= now() or e.valutazione is not null) and e.valutazione is not null
		order by a."data" desc
	);

end;
$$;


ALTER FUNCTION progetto_esame.produci_carriera_completa_studente_storico(s integer) OWNER TO progetto;

--
-- TOC entry 257 (class 1255 OID 16745)
-- Name: produci_carriera_valida_studente(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.produci_carriera_valida_studente(s integer) RETURNS TABLE(appello integer, data_appello date, valutazione progetto_esame.valutazione_esame, cdl character varying, codice_insegnamento character varying, nome_insegnamento character varying, descrizione_insegnamento text, anno_insegnamento progetto_esame.anno_insegnamento, email_docente character varying, nome_docente character varying, cognome_docente character varying)
    LANGUAGE plpgsql
    AS $$
declare
insegnamento record;
begin
	
	for insegnamento in (
		-- ottenimento insegnamenti di cui l'utente ha sostenuto almeno un esame passandolo
		select distinct i.corso_laurea, i.codice 
		from insegnamenti i 
		inner join appelli a on i.codice = a.insegnamento and a.corso_laurea = i.corso_laurea 
		inner join esami e on e.appello = a.id
		where e.studente = s and e.valutazione >= 18
	)
	loop 
		
			return query (with appelli_sostenuti as (
				select *
				from appelli a 
				inner join esami e on e.appello = a.id 
				where e.studente = s 
					and a.insegnamento = insegnamento.codice
					and a.corso_laurea = insegnamento.corso_laurea
			)
			select aps.id, aps.data, aps.valutazione, i.corso_laurea, i.codice, i.nome, i.descrizione, i.anno, d.email, d.nome, d.cognome
			from appelli_sostenuti aps
			inner join insegnamenti i on i.corso_laurea = aps.corso_laurea and i.codice = aps.insegnamento
			inner join docenti d on d.email = i.docente
			where aps.valutazione >= 18 and aps."data" = (select max(data) from appelli_sostenuti));
		
	end loop;
	
end;
$$;


ALTER FUNCTION progetto_esame.produci_carriera_valida_studente(s integer) OWNER TO progetto;

--
-- TOC entry 261 (class 1255 OID 16804)
-- Name: produci_carriera_valida_studente_storico(integer); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.produci_carriera_valida_studente_storico(s integer) RETURNS TABLE(appello integer, data_appello date, valutazione progetto_esame.valutazione_esame, cdl character varying, codice_insegnamento character varying, nome_insegnamento character varying, descrizione_insegnamento text, anno_insegnamento progetto_esame.anno_insegnamento, email_docente character varying, nome_docente character varying, cognome_docente character varying)
    LANGUAGE plpgsql
    AS $$
declare
insegnamento record;
begin
	
	for insegnamento in (
		-- ottenimento insegnamenti di cui l'utente ha sostenuto almeno un esame passandolo
		select distinct i.corso_laurea, i.codice 
		from insegnamenti i 
		inner join appelli a on i.codice = a.insegnamento and a.corso_laurea = i.corso_laurea 
		inner join storico_esami e on e.appello = a.id
		where e.studente = s and e.valutazione >= 18
	)
	loop 
		
			return query (with appelli_sostenuti as (
				select *
				from appelli a 
				inner join esami e on e.appello = a.id 
				where e.studente = s 
					and a.insegnamento = insegnamento.codice
					and a.corso_laurea = insegnamento.corso_laurea
			)
			select aps.id, aps.data, aps.valutazione, i.corso_laurea, i.codice, i.nome, i.descrizione, i.anno, d.email, d.nome, d.cognome
			from appelli_sostenuti aps
			inner join insegnamenti i on i.corso_laurea = aps.corso_laurea and i.codice = aps.insegnamento
			inner join docenti d on d.email = i.docente
			where aps.valutazione >= 18 and aps."data" = (select max(data) from appelli_sostenuti));
		
	end loop;
	
end;
$$;


ALTER FUNCTION progetto_esame.produci_carriera_valida_studente_storico(s integer) OWNER TO progetto;

--
-- TOC entry 247 (class 1255 OID 16724)
-- Name: produci_informazioni_corso_laurea(character varying); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.produci_informazioni_corso_laurea(cdl character varying) RETURNS TABLE(codice character varying, nome character varying, descrizione text, anno progetto_esame.anno_insegnamento, email_docente character varying, nome_docente character varying, cognome_docente character varying)
    LANGUAGE plpgsql
    AS $$
declare esito esami%rowtype;
begin
	
	return query (
		select i.codice, i.nome, i.descrizione, i.anno, d.email, d.nome, d.cognome 
		from insegnamenti i 
		inner join docenti d on d.email = i.docente 
		where i.corso_laurea = cdl
		order by anno asc
	);

end;
$$;


ALTER FUNCTION progetto_esame.produci_informazioni_corso_laurea(cdl character varying) OWNER TO progetto;

--
-- TOC entry 245 (class 1255 OID 16801)
-- Name: salvataggio_studente_in_storico(); Type: FUNCTION; Schema: progetto_esame; Owner: progetto
--

CREATE FUNCTION progetto_esame.salvataggio_studente_in_storico() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
declare
e esami%rowtype;
begin 

	insert into storico_studenti values (old.matricola, old.email, old.nome, old.cognome, old.corso_laurea);
	
	for e in (select * from esami where studente = old.matricola)
	loop 
		-- inserimento in storico dell'esame
		insert into storico_esami values (e.studente, e.appello, e.valutazione);
		-- n.b. rimozione non necessaria in quanto il campo esame.studente è on delete cascade
	end loop;
	
	return old;
	
end;
$$;


ALTER FUNCTION progetto_esame.salvataggio_studente_in_storico() OWNER TO progetto;

--
-- TOC entry 221 (class 1259 OID 16609)
-- Name: appelli; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.appelli (
    id integer NOT NULL,
    insegnamento character varying(15) NOT NULL,
    corso_laurea character varying(15) NOT NULL,
    data date NOT NULL
);


ALTER TABLE progetto_esame.appelli OWNER TO progetto;

--
-- TOC entry 220 (class 1259 OID 16608)
-- Name: appelli_id_seq; Type: SEQUENCE; Schema: progetto_esame; Owner: progetto
--

CREATE SEQUENCE progetto_esame.appelli_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE progetto_esame.appelli_id_seq OWNER TO progetto;

--
-- TOC entry 3483 (class 0 OID 0)
-- Dependencies: 220
-- Name: appelli_id_seq; Type: SEQUENCE OWNED BY; Schema: progetto_esame; Owner: progetto
--

ALTER SEQUENCE progetto_esame.appelli_id_seq OWNED BY progetto_esame.appelli.id;


--
-- TOC entry 217 (class 1259 OID 16406)
-- Name: corsi_laurea; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.corsi_laurea (
    codice character varying(15) NOT NULL,
    nome character varying(128) NOT NULL,
    tipo progetto_esame.tipo_corso_laurea NOT NULL
);


ALTER TABLE progetto_esame.corsi_laurea OWNER TO progetto;

--
-- TOC entry 216 (class 1259 OID 16396)
-- Name: docenti; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.docenti (
    email character varying(254) NOT NULL,
    nome character varying(128) NOT NULL,
    cognome character varying(128) NOT NULL,
    password character varying(128) NOT NULL
);


ALTER TABLE progetto_esame.docenti OWNER TO progetto;

--
-- TOC entry 226 (class 1259 OID 16670)
-- Name: esami; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.esami (
    studente integer NOT NULL,
    appello integer NOT NULL,
    valutazione progetto_esame.valutazione_esame
);


ALTER TABLE progetto_esame.esami OWNER TO progetto;

--
-- TOC entry 225 (class 1259 OID 16669)
-- Name: esame_appello_seq; Type: SEQUENCE; Schema: progetto_esame; Owner: progetto
--

CREATE SEQUENCE progetto_esame.esame_appello_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE progetto_esame.esame_appello_seq OWNER TO progetto;

--
-- TOC entry 3494 (class 0 OID 0)
-- Dependencies: 225
-- Name: esame_appello_seq; Type: SEQUENCE OWNED BY; Schema: progetto_esame; Owner: progetto
--

ALTER SEQUENCE progetto_esame.esame_appello_seq OWNED BY progetto_esame.esami.appello;


--
-- TOC entry 224 (class 1259 OID 16668)
-- Name: esame_studente_seq; Type: SEQUENCE; Schema: progetto_esame; Owner: progetto
--

CREATE SEQUENCE progetto_esame.esame_studente_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE progetto_esame.esame_studente_seq OWNER TO progetto;

--
-- TOC entry 3495 (class 0 OID 0)
-- Dependencies: 224
-- Name: esame_studente_seq; Type: SEQUENCE OWNED BY; Schema: progetto_esame; Owner: progetto
--

ALTER SEQUENCE progetto_esame.esame_studente_seq OWNED BY progetto_esame.esami.studente;


--
-- TOC entry 227 (class 1259 OID 16750)
-- Name: informazioni_complete_insegnamenti; Type: VIEW; Schema: progetto_esame; Owner: progetto
--

CREATE VIEW progetto_esame.informazioni_complete_insegnamenti AS
 SELECT i.corso_laurea,
    i.nome,
    i.descrizione,
    i.codice,
    i.anno,
    cl.nome AS nome_corso_laurea,
    cl.tipo AS tipo_corso_laurea,
    d.nome AS nome_docente,
    d.email AS email_docente,
    d.cognome AS cognome_docente
   FROM ((progetto_esame.insegnamenti i
     JOIN progetto_esame.docenti d ON (((d.email)::text = (i.docente)::text)))
     JOIN progetto_esame.corsi_laurea cl ON (((i.corso_laurea)::text = (cl.codice)::text)));


ALTER TABLE progetto_esame.informazioni_complete_insegnamenti OWNER TO progetto;

--
-- TOC entry 219 (class 1259 OID 16564)
-- Name: propedeuticità; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame."propedeuticità" (
    codice_insegnamento_propedeutico character varying(15) NOT NULL,
    codice_cdl_insegnamento_propedeutico character varying(15) NOT NULL,
    "codice_insegnamento_propedeuticità" character varying(15) NOT NULL,
    "codice_cdl_insegnamento_propedeuticità" character varying(15) NOT NULL
);


ALTER TABLE progetto_esame."propedeuticità" OWNER TO progetto;

--
-- TOC entry 215 (class 1259 OID 16391)
-- Name: segreteria; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.segreteria (
    email character varying(254) NOT NULL,
    password character varying(128) NOT NULL
);


ALTER TABLE progetto_esame.segreteria OWNER TO progetto;

--
-- TOC entry 229 (class 1259 OID 16784)
-- Name: storico_esami; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.storico_esami (
    studente integer NOT NULL,
    appello integer NOT NULL,
    valutazione progetto_esame.valutazione_esame
);


ALTER TABLE progetto_esame.storico_esami OWNER TO progetto;

--
-- TOC entry 228 (class 1259 OID 16765)
-- Name: storico_studenti; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.storico_studenti (
    matricola integer NOT NULL,
    email character varying(254) NOT NULL,
    nome character varying(128) NOT NULL,
    cognome character varying(128) NOT NULL,
    corso_laurea character varying(15) NOT NULL
);


ALTER TABLE progetto_esame.storico_studenti OWNER TO progetto;

--
-- TOC entry 223 (class 1259 OID 16646)
-- Name: studenti; Type: TABLE; Schema: progetto_esame; Owner: progetto
--

CREATE TABLE progetto_esame.studenti (
    matricola integer NOT NULL,
    email character varying(254) NOT NULL,
    nome character varying(128) NOT NULL,
    cognome character varying(128) NOT NULL,
    password character varying(128) NOT NULL,
    corso_laurea character varying(15) NOT NULL
);


ALTER TABLE progetto_esame.studenti OWNER TO progetto;

--
-- TOC entry 222 (class 1259 OID 16645)
-- Name: studenti_matricola_seq; Type: SEQUENCE; Schema: progetto_esame; Owner: progetto
--

CREATE SEQUENCE progetto_esame.studenti_matricola_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE progetto_esame.studenti_matricola_seq OWNER TO progetto;

--
-- TOC entry 3509 (class 0 OID 0)
-- Dependencies: 222
-- Name: studenti_matricola_seq; Type: SEQUENCE OWNED BY; Schema: progetto_esame; Owner: progetto
--

ALTER SEQUENCE progetto_esame.studenti_matricola_seq OWNED BY progetto_esame.studenti.matricola;


--
-- TOC entry 3256 (class 2604 OID 16612)
-- Name: appelli id; Type: DEFAULT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.appelli ALTER COLUMN id SET DEFAULT nextval('progetto_esame.appelli_id_seq'::regclass);


--
-- TOC entry 3258 (class 2604 OID 16673)
-- Name: esami studente; Type: DEFAULT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.esami ALTER COLUMN studente SET DEFAULT nextval('progetto_esame.esame_studente_seq'::regclass);


--
-- TOC entry 3259 (class 2604 OID 16674)
-- Name: esami appello; Type: DEFAULT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.esami ALTER COLUMN appello SET DEFAULT nextval('progetto_esame.esame_appello_seq'::regclass);


--
-- TOC entry 3257 (class 2604 OID 16649)
-- Name: studenti matricola; Type: DEFAULT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.studenti ALTER COLUMN matricola SET DEFAULT nextval('progetto_esame.studenti_matricola_seq'::regclass);


--
-- TOC entry 3455 (class 0 OID 16609)
-- Dependencies: 221
-- Data for Name: appelli; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.appelli (id, insegnamento, corso_laurea, data) FROM stdin;
93	P	L-01	2023-07-04
94	P	L-01	2023-07-18
95	P	L-01	2023-01-18
96	P	L-01	2023-02-13
97	SAC	L-01	2023-02-23
98	SAC	L-01	2023-03-01
99	SAC	L-01	2023-06-04
100	SAC	L-01	2023-07-07
101	AC	L-01	2023-01-14
102	AC	L-01	2023-02-09
103	AC	L-01	2023-06-04
104	AC	L-01	2023-07-12
105	ASAG	L-01	2023-01-17
106	ASAG	L-01	2023-02-23
107	ASAG	L-01	2023-06-09
108	ASAG	L-01	2023-07-20
85	LI	L-01	2023-01-17
86	LI	L-01	2023-02-22
91	LI	L-01	2023-07-13
92	LI	L-01	2023-07-01
87	SAM	L-01	2023-01-24
88	SAM	L-01	2023-02-19
89	SAM	L-01	2023-07-25
90	SAM	L-01	2023-06-28
109	BGC	L-02	2023-01-12
110	BGC	L-02	2023-02-14
111	BGC	L-02	2023-06-05
112	BGC	L-02	2023-07-02
113	CGI	L-02	2023-01-19
114	CGI	L-02	2023-02-20
115	CGI	L-02	2023-06-06
116	CGI	L-02	2023-07-20
117	B	L-02	2023-01-20
118	B	L-02	2023-02-09
119	B	L-02	2023-06-02
120	B	L-02	2023-07-04
121	MG	L-02	2023-01-15
122	MG	L-02	2023-02-21
123	MG	L-02	2023-06-09
124	MG	L-02	2023-07-22
125	PGIMM	L-02	2023-01-21
126	PGIMM	L-02	2023-02-22
127	PGIMM	L-02	2023-06-03
128	PGIMM	L-02	2023-07-04
129	CFPF	L-02	2023-01-20
130	CFPF	L-02	2023-02-23
131	CFPF	L-02	2023-06-02
132	CFPF	L-02	2023-07-14
133	ACMFMG	LM-6	2023-01-29
134	ACMFMG	LM-6	2023-02-17
135	ACMFMG	LM-6	2023-06-24
136	ACMFMG	LM-6	2023-07-30
137	FCM	LM-6	2023-01-13
138	FCM	LM-6	2023-02-22
139	FCM	LM-6	2023-06-07
140	FCM	LM-6	2023-07-19
141	MCI	LM-6	2023-01-24
142	MCI	LM-6	2023-02-19
143	MCI	LM-6	2023-06-22
144	MCI	LM-6	2023-07-29
145	NUS	LM-6	2023-01-17
146	NUS	LM-6	2023-02-20
147	NUS	LM-6	2023-06-09
148	NUS	LM-6	2023-07-12
149	ABD	LM-18	2023-01-12
150	ABD	LM-18	2023-02-14
151	ABD	LM-18	2023-06-09
152	ABD	LM-18	2023-07-12
153	AI	LM-18	2023-01-18
154	AI	LM-18	2023-02-02
155	AI	LM-18	2023-06-20
156	AI	LM-18	2023-07-29
157	MF	LM-18	2023-01-23
158	MF	LM-18	2023-02-17
159	MF	LM-18	2023-06-19
160	MF	LM-18	2023-07-22
161	PA	LM-18	2023-01-29
162	PA	LM-18	2023-02-05
163	PA	LM-18	2023-06-13
164	PA	LM-18	2023-07-30
\.


--
-- TOC entry 3451 (class 0 OID 16406)
-- Dependencies: 217
-- Data for Name: corsi_laurea; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.corsi_laurea (codice, nome, tipo) FROM stdin;
L-02	Biotecnologie	T
LM-6	Biologia	M
LM-18	Informatica	M
L-01	Beni Culturali	T
\.


--
-- TOC entry 3450 (class 0 OID 16396)
-- Dependencies: 216
-- Data for Name: docenti; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.docenti (email, nome, cognome, password) FROM stdin;
emanuele.toma@uniesempio.it	Emanuele	Toma	emanuele.toma
francesco.toma@uniesempio.it	Francesco	Toma	francesco.toma
mario.presterà@uniesempio.it	Mario	Presterà	mario.presterà
alberto.bentoglio@uniesempio.it	Alberto	Bentoglio	alberto.bentoglio
enrico.puntino@uniesempio.it	Enrico	Puntino	enrico.puntino
maria.tintoria@uniesempio.it	Maria	Tintoria	maria.tintoria
fabrizio.slavazzi@uniesempio.it	Fabrizio	Slavazzi	fabrizio.slavazzi
lucio.sacrepanti@uniesempio.it	Lucio	Sacrepanti	lucio.sacrepanti
matteo.paolillo@uniesempio.it	Matteo	Paolillo	matteo.paolillo
\.


--
-- TOC entry 3460 (class 0 OID 16670)
-- Dependencies: 226
-- Data for Name: esami; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.esami (studente, appello, valutazione) FROM stdin;
10	88	18
10	94	\N
10	97	22
10	98	12
10	108	\N
10	100	\N
10	86	24
11	93	\N
11	91	\N
\.


--
-- TOC entry 3452 (class 0 OID 16513)
-- Dependencies: 218
-- Data for Name: insegnamenti; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.insegnamenti (codice, corso_laurea, nome, descrizione, anno, docente) FROM stdin;
P	L-01	Preistoria	Il corso fornirà gli strumenti per orientarsi nell'articolazione cronologica e territoriale delle culture della preistoria e protostoria europea, nelle loro strutture ideologiche e socioeconomiche, nelle loro realizzazioni artistiche, artigianali ed edilizie, e nella loro autorappresentazione su base cultuale e funeraria.	2	mario.presterà@uniesempio.it
SAC	L-01	Storia dell'Arte Contemporanea	Il corso si propone di presentare agli studenti le coordinate cronologiche fondamentali e le principali prospettive metodologiche utili per introdurli allo studio personale, attraverso il manuale e l'esame delle opere dei musei di Milano, delle principali tendenze e dei principali autori dell'arte dal Neoclassicismo a oggi. 	2	mario.presterà@uniesempio.it
AC	L-01	Antropologia Culturale	L'insegnamento ha lo scopo di fornire agli studenti una solida conoscenza generale dei concetti, dei quadri teorici e degli strumenti metodologici principali dell'Antropologia Culturale, e di metterli nelle condizioni di usare il pensiero antropologico come contributo critico ai loro studi, alle loro ricerche e alle loro attività future in contesti interculturali.	3	enrico.puntino@uniesempio.it
ASAG	L-01	Archeologia e Storia dell'Arte Greca	Il corso contribuisce ad affinare la capacità critica e la sensibilità stilistica dello studente nella lettura del manufatto artistico, con ovvio riferimento alla storia dell'arte greca, e getta solide basi per la successiva conoscenza dello sviluppo storico dell'arte che in vari periodi trae viva ispirazione da modelli e materiali greci.	3	maria.tintoria@uniesempio.it
ABD	LM-18	Architectures for Big Data	The course aims at describing the big data processing framework, both in terms of methodologies and technologies. Part of the lessons will focus on Apache Spark and distributed patterns.	1	lucio.sacrepanti@uniesempio.it
NUS	LM-6	Neuroanatomia Umana e Sperimentale	 L'insegnamento vuole offrire una base teorica per studiare alcuni aspetti della neuroanatomia di specifico interesse nel campo delle ricerche biomediche. Nella prima parte verrà approfondito lo studio del tessuto nervoso e del sistema nervoso centrale. Nella seconda parte, si offrirà una visione integrata del sistema nervoso, con particolare riferimento alla neuroanatomia funzionale dei principali sistemi sensitivi e motori. Nella terza parte verranno affrontate e discusse le principali tecniche di indagine morfologica microscopica del sistema nervoso centrale utilizzate nella sperimentazione animale.	2	matteo.paolillo@uniesempio.it
BGC	L-02	Biologia Generale e Cellulare	Il corso si propone di fornire allo studente le nozioni di base sulle caratteristiche fondamentali degli organismi viventi a partire dalle molecole biologiche, organelli subcellulari, cellule e tessuti, nonché il loro funzionamento, al fine di affrontare qualsiasi insegnamento successivo di area biologica.\r\nL'insegnamento si propone di fornire agli studenti una generale comprensione dei meccanismi molecolari che controllano replicazione, trascrizione, traduzione, maturazione delle proteine e metabolismo cellulare, oltre ad approfondire specifici comportamenti cellulari quali proliferazione, motilità, sopravvivenza e/o morte.\r\nSi intende inoltre fornire agli studenti un approfondimento sui meccanismi di controllo del ciclo cellulare, delle vie di trasduzione del segnale e della trasformazione delle cellule tumorali.\r\nL'attività di didattica frontale sarà completata con attività pratiche inerenti metodiche di citologia e microscopia.\r\nObiettivo formativo dell'insegnamento è quello di sviluppare conoscenze relative alla biologia generale e cellulare di organismi animali e vegetali.	1	maria.tintoria@uniesempio.it
AI	LM-18	Artificial Intelligence	L'insegnamento si propone di fornire i fondamenti teorici, le metodologie e le tecniche dell'intelligenza artificiale per l'elaborazione di informazioni e conoscenza, con specifico riferimento alle reti neurali, ai sistemi fuzzy e alla computazione evolutiva.	1	matteo.paolillo@uniesempio.it
ACMFMG	LM-6	Approcci Cellulari, Molecolari e Funzionali alle Malattie Genetiche	L'insegnamento affronta l'intero percorso che caratterizza lo studio delle malattie genetiche, dalla funzione del gene associato alla malattia allo sviluppo di strategie innovative d'interesse terapeutico. In particolare, si tratteranno i seguenti temi: i) approcci per studiare la funzione di un gene malattia; ii) utilizzo di modelli sperimentali nella ricerca biomedica; iii) strategie per identificare nuovi meccanismi patogenetici e nuovi bersagli farmacologici; vi) progettazione di studi preclinici in modelli animali; v) sistemi di delivery di farmaci e molecole; vii) sperimentazione clinica e terapie avanzate. Inoltre, si affronteranno tematiche scientifiche attuali riguardanti la sperimentazione animale, la valorizzazione dei risultati della ricerca, la Research Integrity e l'importanza degli strumenti di supporto alla ricerca (i finanziamenti, il processo peer-review e la divulgazione scientifica).	1	enrico.puntino@uniesempio.it
MF	LM-18	Metodi Formali	L'insegnamento intende esplorare le tecniche formali per migliorare l'affidabilità del software, con un focus particolare sulla specifica e dimostrazione di proprietà del software. Gli strumenti scelti sono i model checker simbolici e la della dimostrazione assistita dal calcolatore.	2	lucio.sacrepanti@uniesempio.it
PA	LM-18	Programmazione Avanzata	L'insegnamento ha l'obiettivo di esporre gli studenti ad alcune tecniche e costrutti avanzati di programmazione, di dimostrarne l'applicazione nella soluzione di specifici problemi e di stimolare e migliorare il proprio pensiero critico quando applicato nella risoluzione di problemi anche complessi.	2	matteo.paolillo@uniesempio.it
MCI	LM-6	Microbiologia Cellulare e Immunologia	L'insegnamento si pone l'obiettivo di fornire agli studenti una conoscenza approfondita dei principali meccanismi di interazione ospite-patogeno. Il corso affronta, partendo dalle differenze fondamentali tra cellula eucariotica e procariotica, i concetti inerenti alle strutture fondamentali e accessorie della cellula batterica, la genetica e il metabolismo della cellula batterica, e infine i concetti di fattori di virulenza e patogenicità batterica con particolare riferimento a malattie infettive e a trasmissione alimentare.\r\nLe lezioni tratteranno in modo dettagliato le principali componenti del sistema immunitario innato ed adattativo implicate nel riconoscimento e nell'eliminazione dei patogeni, facendo particolare attenzione ai vari meccanismi di escape immunologico, infiammazione ed infezione cronica.\r\nInoltre il corso avrà come obiettivo formativo quello di sviluppare conoscenze sulle funzioni del microbiota sia dal punto di vista microbiologico (composizione e funzione dei microrganismi) che dal punto di vista immunologico (funzioni strutturali e protettive), approfondendo poi il concetto di disbiosi e di interazione microbiota-cervello (Gut-brain axis) con esempi specifici delle principali patologie correlate.	2	fabrizio.slavazzi@uniesempio.it
CGI	L-02	Chimica Generale e Inorganica	Il corso si propone di fornire i fondamenti di Chimica Generale e Inorganica indispensabili per la comprensione degli insegnamenti per i quali è propedeutico. Le esercitazioni di laboratorio, inoltre, forniranno allo studente le competenze di base fondamentali per affrontare i corsi di laboratorio successivi. Ultimo obiettivo del corso, ma non meno importante, è far apprezzare allo studente l'importanza della chimica nella società in generale e nella vita di tutti giorni. 	1	enrico.puntino@uniesempio.it
LI	L-01	Letteratura Italiana	L'insegnamento si propone di fornire agli studenti una conoscenza critica degli snodi fondamentali del sistema letterario italiano, dalle Origini al primo Ottocento, seguendo la tradizione e trasformazione di modelli, temi, forme.	1	emanuele.toma@uniesempio.it
SAM	L-01	Storia dell'Arte Medievale	Il corso intende fornire un approccio critico alla cultura artistica occidentale nel millennio che intercorre fra la nascita dell'arte cristiana e il XIII secolo, con particolare attenzione per i contesti monumentali, per l'iconografia e la funzione dei manufatti, il tutto entro un quadro storico e cronologico.	1	mario.presterà@uniesempio.it
B	L-02	Biochimica	Obiettivo del corso è fornire le conoscenze necessarie per la comprensione a livello molecolare dei processi biochimici alla base delle funzioni della cellula e dell'organismo. Gli argomenti del corso trattati nelle lezioni frontali permetteranno di comprendere le proprietà fondamentali delle biomolecole e di come queste interagiscano per mantenere e propagare le caratteristiche di ogni organismo vivente. Le attività pratiche effettuate nelle esercitazioni di laboratorio permetteranno di apprendere i fondamenti di alcune tecniche biochimiche impiegate nella ricerca in campo biologico e biomedico.	2	emanuele.toma@uniesempio.it
MG	L-02	Microbiologia Generale	Il corso si propone di fornire allo studente le conoscenze di base in campo microbiologico, e le generali informazioni necessarie all'utilizzo di metodologie microbiologiche connesse alla biotecnologia. Vengono forniti elementi e approfondimenti di struttura cellulare, fisiologia e genetica microbica, di potenzialità applicative dei microrganismi nella biotecnologia e di studio degli organismi patogeni (sia batteri che virus) e dei loro meccanismi di virulenza.	2	francesco.toma@uniesempio.it
PGIMM	L-02	Patologia Generale, Immunologia e Microbiologia Medica	Il corso integrato di Patologia Generale, Immunologia e Microbiologia Medica si propone di fornire agli studenti del Corso di laurea in Biotecnologia, curriculum Farmaceutico, gli elementi per studiare le basi molecolari e la patogenesi delle malattie umane, infettive e non. Inoltre, il corso fornirà le basi per comprendere i meccanismi delle difese immuni, innate e acquisite, messe in atto dall'ospite. Il modulo di Microbiologia Medica ha lo scopo di approfondire le conoscenze su struttura, replicazione, meccanismi di patogenicità, diagnosi e terapia di virus, batteri e parassiti maggiormente noti per essere causa di infezione nell'uomo.	3	fabrizio.slavazzi@uniesempio.it
CFPF	L-02	Chimica Farmaceutica e Processi Fermentativi	Il corso di Chimica Farmaceutica e Processi Fermentativi, inserito nel piano didattico del curriculum Farmaceutico del Corso di Laurea in Biotecnologia si pone come obiettivo di fornire allo studente:\r\na) le conoscenze che stanno alla base della progettazione e sviluppo di nuovi principi attivi aventi i requisiti necessari per poter diventare dei candidati farmaci.\r\nb) le nozioni necessarie a comprendere i principi che regolano i processi fermentativi nella produzione di farmaci di origine naturale o biotecnologici.\r\nInoltre, tramite la descrizione e l'analisi approfondita di alcune classi di farmaci e di tutte le fasi necessarie all'allestimento di un processo su scala industriale, il corso si propone di fornire agli studenti gli elementi necessari a comprendere le criticità riscontrabili nell'intero processo che porta alla produzione e sviluppo di un farmaco.	3	alberto.bentoglio@uniesempio.it
FCM	LM-6	Fisiologia Cellulare e Molecolare	L'obiettivo dell'insegnamento è quello di approfondire le conoscenze degli studenti sui meccanismi fisiologici cellulari e molecolari specialmente in relazione al fenomeno dell'eccitabilità. Verranno presi in esame alcuni esempi di patologie dovute ad alterazioni nelle componenti molecolari delle cellule eccitabili con particolare attenzione ai canali ionici e la loro interazione con il citoscheletro e altri macro-complessi proteici. La formula di insegnamento è quella del "risolvere il problema". Agli studenti è proposto un meccanismo fisiologico che si compone di diverse interazioni (p.es.: potenziale di\r\nmembrana, rilascio di calcio, attivazione di pathways intracellulari. Questo percorso porterà lo studente a conoscere a fondo il funzionamento delle cellule e come l'alterazione di componenti specifiche possano causare uno stato fisiopatologico e le contromisure da adottare per ripristinare l'omeostasi cellulare e d'organo.	1	maria.tintoria@uniesempio.it
\.


--
-- TOC entry 3453 (class 0 OID 16564)
-- Dependencies: 219
-- Data for Name: propedeuticità; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame."propedeuticità" (codice_insegnamento_propedeutico, codice_cdl_insegnamento_propedeutico, "codice_insegnamento_propedeuticità", "codice_cdl_insegnamento_propedeuticità") FROM stdin;
BGC	L-02	B	L-02
CGI	L-02	CFPF	L-02
SAC	L-01	AC	L-01
LI	L-01	SAC	L-01
\.


--
-- TOC entry 3449 (class 0 OID 16391)
-- Dependencies: 215
-- Data for Name: segreteria; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.segreteria (email, password) FROM stdin;
segreteria1@uniesempio.it	segreteria1
segreteria2@uniesempio.it	segreteria2
segreteria3@uniesempio.it	segreteria3
\.


--
-- TOC entry 3462 (class 0 OID 16784)
-- Dependencies: 229
-- Data for Name: storico_esami; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.storico_esami (studente, appello, valutazione) FROM stdin;
\.


--
-- TOC entry 3461 (class 0 OID 16765)
-- Dependencies: 228
-- Data for Name: storico_studenti; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.storico_studenti (matricola, email, nome, cognome, corso_laurea) FROM stdin;
\.


--
-- TOC entry 3457 (class 0 OID 16646)
-- Dependencies: 223
-- Data for Name: studenti; Type: TABLE DATA; Schema: progetto_esame; Owner: progetto
--

COPY progetto_esame.studenti (matricola, email, nome, cognome, password, corso_laurea) FROM stdin;
12	andrea.ippolito@uniesempio.it	Andrea	Ippolito	andrea.ippolito	L-02
13	marina.huang@uniesempio.it	Marina	Huang	marina.huang	L-02
14	ermenegildo.patriarca@uniesempio.it	Ermenegildo	Patriarca	ermenegildo.patriarca	LM-6
15	lucia.crispaldini@uniesempio.it	Lucia	Crispaldini	lucia.crispaldini	LM-6
16	alessia.terrini@uniesempio.it	Alessia	Terrini	alessia.terrini	LM-18
17	paolo.marnicoldi@uniesempio.it	Paolo	Marnicoldi	paolo.marnicoldi	LM-18
10	federico.trezzani@uniesempio.it	Federico	Trezzani	federico.trezzani	L-01
11	riccardo.maiorino@uniesempio.it	Riccardo	Maiorino	riccardo.maiorino	L-01
\.


--
-- TOC entry 3511 (class 0 OID 0)
-- Dependencies: 220
-- Name: appelli_id_seq; Type: SEQUENCE SET; Schema: progetto_esame; Owner: progetto
--

SELECT pg_catalog.setval('progetto_esame.appelli_id_seq', 165, true);


--
-- TOC entry 3512 (class 0 OID 0)
-- Dependencies: 225
-- Name: esame_appello_seq; Type: SEQUENCE SET; Schema: progetto_esame; Owner: progetto
--

SELECT pg_catalog.setval('progetto_esame.esame_appello_seq', 1, false);


--
-- TOC entry 3513 (class 0 OID 0)
-- Dependencies: 224
-- Name: esame_studente_seq; Type: SEQUENCE SET; Schema: progetto_esame; Owner: progetto
--

SELECT pg_catalog.setval('progetto_esame.esame_studente_seq', 1, false);


--
-- TOC entry 3514 (class 0 OID 0)
-- Dependencies: 222
-- Name: studenti_matricola_seq; Type: SEQUENCE SET; Schema: progetto_esame; Owner: progetto
--

SELECT pg_catalog.setval('progetto_esame.studenti_matricola_seq', 20, true);


--
-- TOC entry 3271 (class 2606 OID 16614)
-- Name: appelli appelli_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.appelli
    ADD CONSTRAINT appelli_pkey PRIMARY KEY (id);


--
-- TOC entry 3265 (class 2606 OID 16412)
-- Name: corsi_laurea corsi_laurea_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.corsi_laurea
    ADD CONSTRAINT corsi_laurea_pkey PRIMARY KEY (codice);


--
-- TOC entry 3263 (class 2606 OID 16402)
-- Name: docenti docenti_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.docenti
    ADD CONSTRAINT docenti_pkey PRIMARY KEY (email);


--
-- TOC entry 3278 (class 2606 OID 16676)
-- Name: esami esame_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.esami
    ADD CONSTRAINT esame_pkey PRIMARY KEY (studente, appello);


--
-- TOC entry 3267 (class 2606 OID 16519)
-- Name: insegnamenti insegnamenti_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.insegnamenti
    ADD CONSTRAINT insegnamenti_pkey PRIMARY KEY (codice, corso_laurea);


--
-- TOC entry 3269 (class 2606 OID 16568)
-- Name: propedeuticità propedeuticità_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame."propedeuticità"
    ADD CONSTRAINT "propedeuticità_pkey" PRIMARY KEY (codice_insegnamento_propedeutico, codice_cdl_insegnamento_propedeutico, "codice_insegnamento_propedeuticità", "codice_cdl_insegnamento_propedeuticità");


--
-- TOC entry 3261 (class 2606 OID 16395)
-- Name: segreteria segreteria_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.segreteria
    ADD CONSTRAINT segreteria_pkey PRIMARY KEY (email);


--
-- TOC entry 3282 (class 2606 OID 16788)
-- Name: storico_esami storico_esami_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.storico_esami
    ADD CONSTRAINT storico_esami_pkey PRIMARY KEY (studente, appello);


--
-- TOC entry 3280 (class 2606 OID 16771)
-- Name: storico_studenti storico_studenti_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.storico_studenti
    ADD CONSTRAINT storico_studenti_pkey PRIMARY KEY (matricola);


--
-- TOC entry 3274 (class 2606 OID 16655)
-- Name: studenti studenti_email_key; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.studenti
    ADD CONSTRAINT studenti_email_key UNIQUE (email);


--
-- TOC entry 3276 (class 2606 OID 16653)
-- Name: studenti studenti_pkey; Type: CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.studenti
    ADD CONSTRAINT studenti_pkey PRIMARY KEY (matricola);


--
-- TOC entry 3272 (class 1259 OID 16620)
-- Name: idx_appelli; Type: INDEX; Schema: progetto_esame; Owner: progetto
--

CREATE UNIQUE INDEX idx_appelli ON progetto_esame.appelli USING btree (insegnamento, corso_laurea, data);


--
-- TOC entry 3295 (class 2620 OID 16706)
-- Name: insegnamenti controllo_anno_insegnamento; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER controllo_anno_insegnamento BEFORE INSERT OR UPDATE ON progetto_esame.insegnamenti FOR EACH ROW EXECUTE FUNCTION progetto_esame.controllo_anno_insegnamento();


--
-- TOC entry 3296 (class 2620 OID 16823)
-- Name: insegnamenti controllo_numero_insegnamenti_docente; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER controllo_numero_insegnamenti_docente BEFORE INSERT OR UPDATE ON progetto_esame.insegnamenti FOR EACH ROW EXECUTE FUNCTION progetto_esame.controllo_numero_insegnamenti_docente();


--
-- TOC entry 3303 (class 2620 OID 16812)
-- Name: esami controllo_propedeuticità_iscrizione_appello; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER "controllo_propedeuticità_iscrizione_appello" BEFORE INSERT ON progetto_esame.esami FOR EACH ROW EXECUTE FUNCTION progetto_esame."controllo_propedeuticità_iscrizione_appello"();


--
-- TOC entry 3300 (class 2620 OID 16755)
-- Name: appelli previeni_appelli_per_insegnamenti_stesso_anno; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER previeni_appelli_per_insegnamenti_stesso_anno BEFORE INSERT OR UPDATE ON progetto_esame.appelli FOR EACH ROW EXECUTE FUNCTION progetto_esame.previeni_appelli_per_insegnamenti_stesso_anno();


--
-- TOC entry 3301 (class 2620 OID 16821)
-- Name: appelli previeni_eliminazione_appello_passato; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER previeni_eliminazione_appello_passato BEFORE DELETE ON progetto_esame.appelli FOR EACH ROW EXECUTE FUNCTION progetto_esame.previeni_eliminazione_appello_passato();


--
-- TOC entry 3297 (class 2620 OID 16688)
-- Name: propedeuticità previeni_inserimento_propedeuticità_cdl_differenti; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER "previeni_inserimento_propedeuticità_cdl_differenti" BEFORE INSERT OR UPDATE ON progetto_esame."propedeuticità" FOR EACH ROW EXECUTE FUNCTION progetto_esame."previeni_inserimento_propedeuticità_cdl_differenti"();


--
-- TOC entry 3298 (class 2620 OID 16696)
-- Name: propedeuticità previeni_inserimento_propedeuticità_medesimo_insegnamento; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER "previeni_inserimento_propedeuticità_medesimo_insegnamento" BEFORE INSERT OR UPDATE ON progetto_esame."propedeuticità" FOR EACH ROW EXECUTE FUNCTION progetto_esame."previeni_inserimento_propedeuticità_medesimo_insegnamento"();


--
-- TOC entry 3299 (class 2620 OID 16807)
-- Name: propedeuticità previeni_inserimento_propedeuticità_transitive_loop; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER "previeni_inserimento_propedeuticità_transitive_loop" BEFORE INSERT OR UPDATE ON progetto_esame."propedeuticità" FOR EACH ROW EXECUTE FUNCTION progetto_esame."previeni_inserimento_propedeuticità_transitive_loop"();


--
-- TOC entry 3304 (class 2620 OID 16813)
-- Name: esami previeni_iscrizione_appello_data_passata; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER previeni_iscrizione_appello_data_passata BEFORE INSERT ON progetto_esame.esami FOR EACH ROW EXECUTE FUNCTION progetto_esame.previeni_iscrizione_appello_data_passata();


--
-- TOC entry 3305 (class 2620 OID 16814)
-- Name: esami previeni_iscrizione_appello_insegnamento_non_in_cdl; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER previeni_iscrizione_appello_insegnamento_non_in_cdl BEFORE INSERT ON progetto_esame.esami FOR EACH ROW EXECUTE FUNCTION progetto_esame.previeni_iscrizione_appello_insegnamento_non_in_cdl();


--
-- TOC entry 3294 (class 2620 OID 16853)
-- Name: corsi_laurea previeni_modifica_tipo_corso_laurea_insegnamenti_discordi; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER previeni_modifica_tipo_corso_laurea_insegnamenti_discordi BEFORE UPDATE ON progetto_esame.corsi_laurea FOR EACH ROW EXECUTE FUNCTION progetto_esame.previeni_modifica_tipo_corso_laurea_insegnamenti_discordi();


--
-- TOC entry 3302 (class 2620 OID 16802)
-- Name: studenti salvataggio_studente_in_storico; Type: TRIGGER; Schema: progetto_esame; Owner: progetto
--

CREATE TRIGGER salvataggio_studente_in_storico BEFORE DELETE ON progetto_esame.studenti FOR EACH ROW EXECUTE FUNCTION progetto_esame.salvataggio_studente_in_storico();


--
-- TOC entry 3287 (class 2606 OID 16615)
-- Name: appelli appelli_insegnamento; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.appelli
    ADD CONSTRAINT appelli_insegnamento FOREIGN KEY (insegnamento, corso_laurea) REFERENCES progetto_esame.insegnamenti(codice, corso_laurea) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3289 (class 2606 OID 16682)
-- Name: esami fk_esame_appello; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.esami
    ADD CONSTRAINT fk_esame_appello FOREIGN KEY (appello) REFERENCES progetto_esame.appelli(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3290 (class 2606 OID 16677)
-- Name: esami fk_esame_studente; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.esami
    ADD CONSTRAINT fk_esame_studente FOREIGN KEY (studente) REFERENCES progetto_esame.studenti(matricola) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3283 (class 2606 OID 16520)
-- Name: insegnamenti fk_insegnamenti_corso_laurea; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.insegnamenti
    ADD CONSTRAINT fk_insegnamenti_corso_laurea FOREIGN KEY (corso_laurea) REFERENCES progetto_esame.corsi_laurea(codice) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3284 (class 2606 OID 16525)
-- Name: insegnamenti fk_insegnamenti_docente; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.insegnamenti
    ADD CONSTRAINT fk_insegnamenti_docente FOREIGN KEY (docente) REFERENCES progetto_esame.docenti(email) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3285 (class 2606 OID 16569)
-- Name: propedeuticità fk_propedeuticità_insegnamento_propedeuticità; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame."propedeuticità"
    ADD CONSTRAINT "fk_propedeuticità_insegnamento_propedeuticità" FOREIGN KEY (codice_insegnamento_propedeutico, "codice_cdl_insegnamento_propedeuticità") REFERENCES progetto_esame.insegnamenti(codice, corso_laurea) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3286 (class 2606 OID 16574)
-- Name: propedeuticità fk_propedeuticità_insegnamento_propedeutico; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame."propedeuticità"
    ADD CONSTRAINT "fk_propedeuticità_insegnamento_propedeutico" FOREIGN KEY (codice_insegnamento_propedeutico, codice_cdl_insegnamento_propedeutico) REFERENCES progetto_esame.insegnamenti(codice, corso_laurea) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3292 (class 2606 OID 16789)
-- Name: storico_esami fk_storico_esami_appello; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.storico_esami
    ADD CONSTRAINT fk_storico_esami_appello FOREIGN KEY (appello) REFERENCES progetto_esame.appelli(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3293 (class 2606 OID 16794)
-- Name: storico_esami fk_storico_esami_studente; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.storico_esami
    ADD CONSTRAINT fk_storico_esami_studente FOREIGN KEY (studente) REFERENCES progetto_esame.storico_studenti(matricola) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3291 (class 2606 OID 16827)
-- Name: storico_studenti fk_storico_studenti_corso_laurea; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.storico_studenti
    ADD CONSTRAINT fk_storico_studenti_corso_laurea FOREIGN KEY (corso_laurea) REFERENCES progetto_esame.corsi_laurea(codice) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3288 (class 2606 OID 16832)
-- Name: studenti fk_studenti_corso_laurea; Type: FK CONSTRAINT; Schema: progetto_esame; Owner: progetto
--

ALTER TABLE ONLY progetto_esame.studenti
    ADD CONSTRAINT fk_studenti_corso_laurea FOREIGN KEY (corso_laurea) REFERENCES progetto_esame.corsi_laurea(codice) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3468 (class 0 OID 0)
-- Dependencies: 6
-- Name: SCHEMA progetto_esame; Type: ACL; Schema: -; Owner: progetto
--

GRANT USAGE ON SCHEMA progetto_esame TO docente;
GRANT USAGE ON SCHEMA progetto_esame TO studente;
GRANT USAGE ON SCHEMA progetto_esame TO segreteria;


--
-- TOC entry 3469 (class 0 OID 0)
-- Dependencies: 263
-- Name: PROCEDURE delete_docente(IN doc character varying, IN cl_arr character varying[], IN ins_arr character varying[], IN doc_arr character varying[]); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON PROCEDURE progetto_esame.delete_docente(IN doc character varying, IN cl_arr character varying[], IN ins_arr character varying[], IN doc_arr character varying[]) TO segreteria;


--
-- TOC entry 3470 (class 0 OID 0)
-- Dependencies: 260
-- Name: FUNCTION get_appelli_studente_non_iscritto_futuri(s integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.get_appelli_studente_non_iscritto_futuri(s integer) TO studente;


--
-- TOC entry 3471 (class 0 OID 0)
-- Dependencies: 218
-- Name: TABLE insegnamenti; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT ON TABLE progetto_esame.insegnamenti TO docente;
GRANT SELECT ON TABLE progetto_esame.insegnamenti TO studente;
GRANT SELECT,INSERT,UPDATE ON TABLE progetto_esame.insegnamenti TO segreteria;


--
-- TOC entry 3472 (class 0 OID 0)
-- Dependencies: 250
-- Name: FUNCTION get_insegnamenti_a_cui_propedeutico(cl character varying, ci character varying); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.get_insegnamenti_a_cui_propedeutico(cl character varying, ci character varying) TO segreteria;


--
-- TOC entry 3473 (class 0 OID 0)
-- Dependencies: 255
-- Name: FUNCTION get_insegnamenti_aggiungibili_come_propedeutici(cl character varying, ci character varying); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.get_insegnamenti_aggiungibili_come_propedeutici(cl character varying, ci character varying) TO segreteria;


--
-- TOC entry 3474 (class 0 OID 0)
-- Dependencies: 251
-- Name: FUNCTION get_insegnamenti_propedeutici(cl character varying, ci character varying); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.get_insegnamenti_propedeutici(cl character varying, ci character varying) TO segreteria;


--
-- TOC entry 3475 (class 0 OID 0)
-- Dependencies: 249
-- Name: FUNCTION get_iscrizioni_appello(id_appello integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.get_iscrizioni_appello(id_appello integer) TO docente;


--
-- TOC entry 3476 (class 0 OID 0)
-- Dependencies: 256
-- Name: FUNCTION get_iscrizioni_attive_appelli_studente(s integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.get_iscrizioni_attive_appelli_studente(s integer) TO studente;


--
-- TOC entry 3477 (class 0 OID 0)
-- Dependencies: 248
-- Name: FUNCTION produci_carriera_completa_studente(s integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.produci_carriera_completa_studente(s integer) TO segreteria;
GRANT ALL ON FUNCTION progetto_esame.produci_carriera_completa_studente(s integer) TO studente;


--
-- TOC entry 3478 (class 0 OID 0)
-- Dependencies: 246
-- Name: FUNCTION produci_carriera_completa_studente_storico(s integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.produci_carriera_completa_studente_storico(s integer) TO segreteria;
GRANT ALL ON FUNCTION progetto_esame.produci_carriera_completa_studente_storico(s integer) TO studente;


--
-- TOC entry 3479 (class 0 OID 0)
-- Dependencies: 257
-- Name: FUNCTION produci_carriera_valida_studente(s integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.produci_carriera_valida_studente(s integer) TO segreteria;
GRANT ALL ON FUNCTION progetto_esame.produci_carriera_valida_studente(s integer) TO studente;


--
-- TOC entry 3480 (class 0 OID 0)
-- Dependencies: 261
-- Name: FUNCTION produci_carriera_valida_studente_storico(s integer); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.produci_carriera_valida_studente_storico(s integer) TO segreteria;
GRANT ALL ON FUNCTION progetto_esame.produci_carriera_valida_studente_storico(s integer) TO studente;


--
-- TOC entry 3481 (class 0 OID 0)
-- Dependencies: 247
-- Name: FUNCTION produci_informazioni_corso_laurea(cdl character varying); Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT ALL ON FUNCTION progetto_esame.produci_informazioni_corso_laurea(cdl character varying) TO studente;
GRANT ALL ON FUNCTION progetto_esame.produci_informazioni_corso_laurea(cdl character varying) TO segreteria;


--
-- TOC entry 3482 (class 0 OID 0)
-- Dependencies: 221
-- Name: TABLE appelli; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,INSERT,DELETE ON TABLE progetto_esame.appelli TO docente;
GRANT SELECT ON TABLE progetto_esame.appelli TO studente;
GRANT SELECT ON TABLE progetto_esame.appelli TO segreteria;


--
-- TOC entry 3484 (class 0 OID 0)
-- Dependencies: 220
-- Name: SEQUENCE appelli_id_seq; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,USAGE ON SEQUENCE progetto_esame.appelli_id_seq TO docente;


--
-- TOC entry 3485 (class 0 OID 0)
-- Dependencies: 217
-- Name: TABLE corsi_laurea; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,INSERT ON TABLE progetto_esame.corsi_laurea TO segreteria;
GRANT SELECT ON TABLE progetto_esame.corsi_laurea TO studente;


--
-- TOC entry 3486 (class 0 OID 0)
-- Dependencies: 217 3485
-- Name: COLUMN corsi_laurea.nome; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT UPDATE(nome) ON TABLE progetto_esame.corsi_laurea TO segreteria;


--
-- TOC entry 3487 (class 0 OID 0)
-- Dependencies: 217 3485
-- Name: COLUMN corsi_laurea.tipo; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT UPDATE(tipo) ON TABLE progetto_esame.corsi_laurea TO segreteria;


--
-- TOC entry 3488 (class 0 OID 0)
-- Dependencies: 216
-- Name: TABLE docenti; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT ON TABLE progetto_esame.docenti TO docente;
GRANT INSERT,DELETE,UPDATE ON TABLE progetto_esame.docenti TO segreteria;


--
-- TOC entry 3489 (class 0 OID 0)
-- Dependencies: 216 3488
-- Name: COLUMN docenti.email; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(email) ON TABLE progetto_esame.docenti TO studente;
GRANT SELECT(email) ON TABLE progetto_esame.docenti TO segreteria;


--
-- TOC entry 3490 (class 0 OID 0)
-- Dependencies: 216 3488
-- Name: COLUMN docenti.nome; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(nome) ON TABLE progetto_esame.docenti TO studente;
GRANT SELECT(nome) ON TABLE progetto_esame.docenti TO segreteria;


--
-- TOC entry 3491 (class 0 OID 0)
-- Dependencies: 216 3488
-- Name: COLUMN docenti.cognome; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(cognome) ON TABLE progetto_esame.docenti TO studente;
GRANT SELECT(cognome) ON TABLE progetto_esame.docenti TO segreteria;


--
-- TOC entry 3492 (class 0 OID 0)
-- Dependencies: 216 3488
-- Name: COLUMN docenti.password; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT UPDATE(password) ON TABLE progetto_esame.docenti TO docente;


--
-- TOC entry 3493 (class 0 OID 0)
-- Dependencies: 226
-- Name: TABLE esami; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,UPDATE ON TABLE progetto_esame.esami TO docente;
GRANT SELECT,INSERT ON TABLE progetto_esame.esami TO studente;
GRANT SELECT ON TABLE progetto_esame.esami TO segreteria;


--
-- TOC entry 3496 (class 0 OID 0)
-- Dependencies: 227
-- Name: TABLE informazioni_complete_insegnamenti; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT ON TABLE progetto_esame.informazioni_complete_insegnamenti TO segreteria;


--
-- TOC entry 3497 (class 0 OID 0)
-- Dependencies: 219
-- Name: TABLE "propedeuticità"; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT ON TABLE progetto_esame."propedeuticità" TO docente;
GRANT SELECT ON TABLE progetto_esame."propedeuticità" TO studente;
GRANT SELECT,INSERT,DELETE ON TABLE progetto_esame."propedeuticità" TO segreteria;


--
-- TOC entry 3498 (class 0 OID 0)
-- Dependencies: 215
-- Name: TABLE segreteria; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT ON TABLE progetto_esame.segreteria TO segreteria;


--
-- TOC entry 3499 (class 0 OID 0)
-- Dependencies: 215 3498
-- Name: COLUMN segreteria.password; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT UPDATE(password) ON TABLE progetto_esame.segreteria TO segreteria;


--
-- TOC entry 3500 (class 0 OID 0)
-- Dependencies: 229
-- Name: TABLE storico_esami; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,INSERT ON TABLE progetto_esame.storico_esami TO segreteria;


--
-- TOC entry 3501 (class 0 OID 0)
-- Dependencies: 228
-- Name: TABLE storico_studenti; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,INSERT,UPDATE ON TABLE progetto_esame.storico_studenti TO segreteria;


--
-- TOC entry 3502 (class 0 OID 0)
-- Dependencies: 223
-- Name: TABLE studenti; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT ON TABLE progetto_esame.studenti TO studente;
GRANT INSERT,DELETE,UPDATE ON TABLE progetto_esame.studenti TO segreteria;


--
-- TOC entry 3503 (class 0 OID 0)
-- Dependencies: 223 3502
-- Name: COLUMN studenti.matricola; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(matricola) ON TABLE progetto_esame.studenti TO docente;
GRANT SELECT(matricola) ON TABLE progetto_esame.studenti TO segreteria;


--
-- TOC entry 3504 (class 0 OID 0)
-- Dependencies: 223 3502
-- Name: COLUMN studenti.email; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(email) ON TABLE progetto_esame.studenti TO docente;
GRANT SELECT(email) ON TABLE progetto_esame.studenti TO segreteria;


--
-- TOC entry 3505 (class 0 OID 0)
-- Dependencies: 223 3502
-- Name: COLUMN studenti.nome; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(nome) ON TABLE progetto_esame.studenti TO docente;
GRANT SELECT(nome) ON TABLE progetto_esame.studenti TO segreteria;


--
-- TOC entry 3506 (class 0 OID 0)
-- Dependencies: 223 3502
-- Name: COLUMN studenti.cognome; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(cognome) ON TABLE progetto_esame.studenti TO docente;
GRANT SELECT(cognome) ON TABLE progetto_esame.studenti TO segreteria;


--
-- TOC entry 3507 (class 0 OID 0)
-- Dependencies: 223 3502
-- Name: COLUMN studenti.password; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT UPDATE(password) ON TABLE progetto_esame.studenti TO studente;


--
-- TOC entry 3508 (class 0 OID 0)
-- Dependencies: 223 3502
-- Name: COLUMN studenti.corso_laurea; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT(corso_laurea) ON TABLE progetto_esame.studenti TO docente;
GRANT SELECT(corso_laurea) ON TABLE progetto_esame.studenti TO segreteria;


--
-- TOC entry 3510 (class 0 OID 0)
-- Dependencies: 222
-- Name: SEQUENCE studenti_matricola_seq; Type: ACL; Schema: progetto_esame; Owner: progetto
--

GRANT SELECT,USAGE ON SEQUENCE progetto_esame.studenti_matricola_seq TO segreteria;


-- Completed on 2023-06-13 21:26:10 CEST

--
-- PostgreSQL database dump complete
--

