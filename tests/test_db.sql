CREATE TABLE thing (
  id SERIAL,
  thing_type varchar(50),
  thing_description TEXT,
  thing_cost decimal(10,4),
  thing_in_stock bool
);

INSERT INTO thing(thing_type, thing_description, thing_cost, thing_in_stock)
    VALUES('pen', NULL, 50.23, 'f');
INSERT INTO thing(thing_type, thing_description, thing_cost, thing_in_stock)
    VALUES('pencil', 'something you write with', 27.50, null);
INSERT INTO thing(thing_type, thing_description, thing_cost, thing_in_stock)
    VALUES('marker', NULL, 50.23, 't');

CREATE TABLE test_bool_param (
  id serial not null,
  b boolean,
  primary key(id)
);
insert into test_bool_param(b) values(true);
