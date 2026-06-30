#!/bin/bash
# Disable conflicting Apache MPM modules on startup
a2dismod mpm_event mpm_worker mpm_prefork 2>/dev/null || true

# Enable only mpm_prefork required for PHP
a2enmod mpm_prefork

# Hand execution over to Apache foreground daemon
exec apache2-foreground
