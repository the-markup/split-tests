FROM wordpress:php8.1

# Dependencies
RUN apt update && apt install -y \
	less \
	mariadb-client \
	subversion \
	unzip \
    emacs-nox;

# Setup
ADD ./setup/web /setup
RUN /setup/wp-cli
RUN /setup/composer
RUN /setup/debug-log
