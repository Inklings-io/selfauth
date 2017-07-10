By contributing to this project, you agree to irrevocably release your contributions under the same licenses as this project. See README.md for more details.



Coding Standards
-----

PHP CodeSniffer is used to normalize all code in this project.
Please install it with
```bash
pear install PHP_CodeSniffer
```

You can then test your code with
```bash
phpcs --standard=ruleset.xml *.php
```
Or you can have it autofix whatever it can with
```bash
phpcbf --standard=ruleset.xml *.php
```

