#!/usr/bin/env bash


vendor/bin/xsd2php convert php tests/complex.wsdl --soap-messages \
    --ns-map='http://www.xignite.com/services/;Gen/' \
    --ns-dest='Gen/;gen' -vvv

vendor/bin/xsd2php convert jms-yaml tests/complex.wsdl --soap-messages \
    --ns-map='http://www.xignite.com/services/;Gen/' \
    --ns-dest='Gen/;gen2' -vvv

#vendor/bin/xsd2php convert:jms-yaml tests/easy.wsdl \
#    --ns-map='http://www.example.org/;Gen/' \
#    --ns-dest='Gen/;gen2'