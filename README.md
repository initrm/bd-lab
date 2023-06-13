# bd-lab

A simple (i.e. imperfect) application that simulates a complete University online portal for students, professors and workers.


## Project Structure

```
.
|
+--> website: contains all the file that power up the website
|  |
|  +--> html: contains public website files
|  |  |
|  |  +--> scripts: contains the JS files
|  |  |
|  |  +--> api: contains the php files which executes a function but do not render a page
|  |  |
|  |  +--> dashboards: contains different dashboards for each user type
|  |
|  +--> components: contains all the php files which renders only components of pages and that are not meant to be used standalone
|
+--> ambiente: contains all the files that are used to run stuff used to run the project
```
## Run Locally

Clone the project

```bash
  git clone git@github.com:initrm/bd-lab.git
```

Go to the project directory

```bash
  cd bd-lab
```

Run the enviroment

```bash
  docker-compose -f ambiente/docker-compose.yml up -d
```

Connect to PostgreSQL which will be now available at `localhost:5432` with username `postgres` and password `unimipgsql` and import the dump located at `ambiente/dump.sql`.

Web app should now available and working at `localhost:8082`.


## License

[MIT](https://choosealicense.com/licenses/mit/)

