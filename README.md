# Drupal Camp delhi

Drupal camp delhi repo is build on top of drupal cms.

## Tools & Prerequisites

The following tools are required for setting up the site.

- [Git](https://git-scm.com/book/en/v2/Getting-Started-Installing-Git) - v2.17.1 or higher
- [Composer](https://getcomposer.org/download/) - v2 or higher
- [ddev](https://github.com/drud/ddev) - v1.17.0
- [nodejs](https://nodejs.org/en/download/) - v16 or higher
- [yarn](https://classic.yarnpkg.com/lang/en/docs/install)

Note: Please make sure you have all above tools in-place before
you start with next tasks.

---

## Setting up the project

### Step 1] Git repository setup:

1. Create a fork of the repository.
2. Clone the project.
3. Setup a SSH key that can be used for Github
   1. [Setup Github SSH Keys](https://docs.github.com/en/authentication/connecting-to-github-with-ssh/adding-a-new-ssh-key-to-your-github-account)

```
    $ git clone <your_fork>
```
4. Add the upstream URL.
```
git remote add upstream git@github.com:guptahemant/dcd-cms.git
```

### Step 2] Local environment (DDEV) setup:

#### Pre-requisites

- Docker
- Docker Compose
- DDEV
- Make sure your ssh pub keys are added to dev server for db sync.

#### Drush Aliases
Use drush aliases to specifically target a local site. List of all aliases can
be found by running:
```
ddev drush sa
```
For finding out the URL of a site use the information in drush alias.

Current local aliases
- ddev.local - Use to target local site.

#### 2.1] Run the following command to setup project

- `ddev start`
- Require all packages `ddev composer install`

#### 2.2] Install drupal site from scratch (Needed to be done for initial setup).
- Check database name using `drush @alias status` (if performing on server).
- Verify database name on acquia cloud.
- Cloud path: Application > Environment > Databases > Database name > Settings > Name.
- `ddev drush si --existing-config -y`
- `ddev drush cim -y`

#### 2.3] Syncing local with remote database (To be updated).
- Authorize ddev to use your keys `ddev auth ssh`
- Take the db dump from the remote site: `ddev drush @alias sql-dump > db_dump.sql`
- Import db dump to a local site: `ddev drush @alias sql-cli < db_dump.sql`
- To login use `ddev drush @alias uli`

#### 2.4] Frontend setup.
- To be added

## Development workflow before starting work on any new task.
Active development branch is `develop`

### Step 1] Take the latest pull from develop branch
- `git checkout develop`
- `git pull upstream develop`

### Step 2] Update your local to reflect all the latest changes.
- `ddev composer install`
- `ddev drush updb -y`
- `ddev drush cim -y`
- FE updates: TBD

### Step 2] Create a feature branch from develop branch.
- `git chechout -b feature/new-branch`
- Perform work on local.
- Export any config changes.
- Make sure while exporting the config changes to properly use the config splits.

### Step 3] Create a pull request for your work.

- https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/creating-a-pull-request

## Troubleshooting
- Use github issues to report any issues you encounter.

## How to pickup issues
- Go to https://github.com/users/guptahemant/projects/3
- Pickup issues from ready / backlog queue
- Add a comment on an issue, if you are workin upon it.
- Once work is completed, Create the PR and make sure to mention the issue id in PR.
- Post the PR link in slack channel for review.
