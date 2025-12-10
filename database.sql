
#LIBRI
CREATE TABLE libri (
    isbn int primary key,
    titolo varchar(255) not null,
    descrizione text,
    ean varchar(20) not null
);

#AUTORI
CREATE TABLE autori(
    id_autore int auto_increment primary key,
    nome varchar(100) not null,
    cognome varchar(100) not null,
    isbn int,
    foreign key (isbn) references libri(isbn)
);

#CATEGORIE
CREATE TABLE categorie(
    nome varchar(100) primary key,
    isbn int,
    foreign key (isbn) references libri(isbn)
);

#COPIE
CREATE TABLE copie(
    id_copia int auto_increment primary key,
    isbn int,
    codice_barre varchar(50) not null,
    condizione varchar(50) not null,
    disponibile boolean default false,
    collocazione varchar(100),
    anno_pubblicazione YEAR not null,
    editore varchar(100) not null,
    copertina varchar(255) not null,
    taf_rfid varchar(100), #url
    foreign key (isbn) references libri(isbn)
);

#UTENTI
CREATE TABLE utenti (
    codice_alfanumerico varchar(50) primary key,
    nome varchar(100) not null,
    cognome varchar(100) not null,
    data_nascita DATE not null,
    sesso char(1) not null,
    comune_nascita varchar(100) not null,
    codice_fiscale char(16) not null,
    email varchar(255) not null,
    password_hash varchar(255) not null,
    tentativi_login int default 0,
    account_bloccato boolean default false,
    data_creazione datetime default current_timestamp,
    affidabile boolean default true
);

#PRESTITI 
CREATE TABLE prestiti (
    id_prestito int auto_increment primary key,
    codice_alfanumerico varchar(50) not null,
    id_copia int not null,
    data_prestito date,
    data_scadenza date,
    data_restituzione date,
    num_rinnovi int default 0,
    terminato boolean default false,
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico),
    foreign key (id_copia) references copie(id_copia) 
);

#MULTE
create table multe (
    id_multa int auto_increment primary key,
    id_prestito int not null,
    codice_alfanumerico varchar(50) not null,
    importo decimal(10,2) not null,
    motivazione text not null,
    pagata boolean default false,
    data_crea datetime default current_timestamp,
    foreign key (id_prestito) references prestiti(id_prestito),
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico)
);

#PRENOTAZIONI
create table prenotazioni (
    id_prenotazione int auto_increment primary key,
    codice_alfanumerico varchar(50) not null,
    isbn int,
    data_prenotazione date,
    data_assegnazione date,
    data_ritiro date,
    posizione_coda int,
    stato varchar(50),
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico),
    foreign key (isbn) references libri(isbn)
);

#RECENSIONI
CREATE TABLE recensioni (
    id_prenotazione int auto_increment primary key,
    isbn int,
    codice_alfanumerico varchar(50) not null,
    voto int not null,
    commento text null,
    data_commento datetime default current_timestamp,
    foreign key (isbn) references libri(isbn),
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico)
);

#ACCESSI_IP
create table accessi_ip(
    indirizzo_ip varchar(45) primary key,
    richieste_effettuate int,
    finestra_inizio datetime
);

#BADGE
create table badge (
    codice_alfanumerico varchar(50) not null,
    nome varchar(100) primary key,
    icona varchar(255) not null,
    descrizione text not null,
    tipo varchar(50) not null,
    target_numerico int not null,
    date_fine date,
    percentuale_completamento decimal(5,2),
    completato boolean default false,
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico)
);

#NOTIFICE
create table notifice (
    id_notifica int auto_increment primary key,
    codice_alfanumerico varchar(50),
    titolo varchar(255) not null,
    messaggio text not null,
    tipo varchar(50) not null,
    data_invio datetime,
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico)
);

#LOG MONITORAGGI
create table log_monitoraggi (
    id_log int auto_increment primary key,
    codice_alfanumerico varchar(50),
    tipo_evento varchar(50),
    descrizione text,
    indirizzo_ip varchar(45),
    data_evento datetime,
    foreign key (codice_alfanumerico) references utenti(codice_alfanumerico)
);

#CONSENSI_GDPR
create table consensi_gpdr (
    id_consenso int auto_increment primary key,
    id_utente varchar(50),
    tipo_consenso varchar(50),
    data_consenso date,
    indirizzo_ip varchar(45),
    foreign key (id_utente) references utenti(codice_alfanumerico)
);

#RUOLI
create table ruoli (
    id_ruolo int auto_increment primary key,
    studente boolean default false,
    docente boolean default false,
    bibliotecario boolean default false,
    amministratore boolean default false
)

