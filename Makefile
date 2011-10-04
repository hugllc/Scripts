PHPUNIT=`which phpunit`
PHPDOC=`which phpdoc`
DOXYGEN=`which doxygen`
PHPCS=`which phpcs`
BASE_DIR=${PWD}/
GIT=`which git`

all: test style

test: clean doc-clean Documentation/test
	cd test; ${PHPUNIT} --coverage-html ${BASE_DIR}Documentation/test/codecoverage/ \
		--log-junit ${BASE_DIR}Documentation/test/log.xml \
		--testdox-html ${BASE_DIR}Documentation/test/testdox.html \
		--bootstrap bootstrap.php \
		.| tee ${BASE_DIR}Documentation/test/testoutput.txt

Documentation/test:
	mkdir -p ${BASE_DIR}Documentation/test


doc:
	rm -Rf ${BASE_DIR}Documentation/Scripts
	mkdir -p ${BASE_DIR}Documentation/Scripts
	echo Building Scripts Docs
	${PHPDOC} -c phpdoc.ini  | tee ${BASE_DIR}Documentation/Scripts.build.txt

doc-clean:
	rm -Rf ${BASE_DIR}Documentation/Scripts

clean:
	rm -Rf *~ */*~ */*/*~ */*/*/*~


style:
	mkdir -p ${BASE_DIR}Documentation/Scripts
	${PHPCS} --standard=PHPCS --report=full --standard=Pear  --ignore="Documentation/,JoomlaMock/,tmpl/,old/,contrib/,html/,build/" .
	
UItest: UItest-CoreUI

