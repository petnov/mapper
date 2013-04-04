Simple ORM for Nette Framework
==============================

Mapper is implementation of Data Mapper pattern. It provides to developer a freedom in writing own SQL queries 
and hydrates objects from it. 

Mapper basic features
- write your SQL, hydrate objects
- metadata mapping with annotations
- lazy-loading of all associations (toOne or toMany)
- cache all results
- identity map implementation (only one instance of every object is hydrated)

Workflow
- Create an entity
- Create database table for it
- Annotate properties that will be stored in columns
- Annotate associations

See examples in mapper_sandbox
