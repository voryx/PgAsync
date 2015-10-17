CREATE TABLE thing (
  id SERIAL,
  thing_type varchar(50),
  thing_description TEXT,
  thing_cost decimal(10,4)
);

INSERT INTO thing(thing_type, thing_description, thing_cost)
    VALUES('pen', NULL, 50.23);
INSERT INTO thing(thing_type, thing_description, thing_cost)
    VALUES('pencil', 'something you write with', 27.50);