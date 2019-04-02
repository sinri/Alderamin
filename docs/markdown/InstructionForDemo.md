# Alderamin Instruction for Demo

Alderamin provided a demo to show how this library works with your great extension.

## Design

You need to ensure the folders to be:

* log store, where the log files would be written in
* craft store, where the csv files would be written in
* report store, where the final excel files would be written in
* sql store, where the definition files would be fetched, including component and report

An extension project with its own namespace is needed, it should contain:

* runner and bootstrap implementation
* extended command line programs
* categories and permissions (optional)
* implemented units, reports and components

## Database

You have to create a scheme called `alderamin` for Alderamin in core database.

And for the demo you need a scheme called `test` in the read/write database nodes.

## Run

Open `/demo/test/action` and run the scripts.