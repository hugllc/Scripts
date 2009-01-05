PHPUNIT=`which phpunit`
PHPDOC=`which phpdoc`
DOXYGEN=`which doxygen`
PHPCS=`which phpcs`
BASE_DIR=../

UItest: UItest-CoreUI

test:
	mkdir -p ${BASE_DIR}Documentation/test
	${PHPUNIT} --report ${BASE_DIR}Documentation/test/codecoverage/ \
                --log-xml ${BASE_DIR}Documentation/test/log.xml \
                --testdox-html ${BASE_DIR}Documentation/test/testdox.html \
                --log-pmd ${BASE_DIR}Documentation/test/pmd.xml \
                --log-metrics ${BASE_DIR}Documentation/test/metrics.xml \
                HUGnetTests | tee ${BASE_DIR}Documentation/test/testoutput.txt	

doc:
	rm -Rf ${BASE_DIR}Documentation/Scripts
	mkdir -p ${BASE_DIR}Documentation/Scripts
	echo Building Scripts Docs
	${PHPDOC} -c phpdoc.ini  | tee ${BASE_DIR}Documentation/Scripts.build.txt

clean:
	rm -Rf ${BASE_DIR}Documentation/Scripts


style:
	mkdir -p ${BASE_DIR}Documentation/Scripts
	${PHPCS} --standard=PHPCS --report=full --standard=Pear .
	
