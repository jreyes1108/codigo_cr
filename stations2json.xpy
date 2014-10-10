import cgi
import json
import codecs
import shutil
import pprint
import hashlib 
from optparse import OptionParser
from math import radians, cos, sin, asin, sqrt

try:
    import antelope.datascope as datascope
    import antelope.stock as stock
    import antelope.coords as coords
except Exception,e:
    print "\nProblemas con el Antilope. [%s]\n" % e
    sys.exit(1)



usage = "Usage: %prog [options]"
parser = OptionParser(usage=usage)
parser.add_option("-p", "--pf", action="store",
        dest="pf", help="parametros", default='stations2json.pf')
parser.add_option("-v", "--verbose", action="store_true",
        dest="verbose", help="verbose output", default=False)
(options, args) = parser.parse_args()

pf = stock.pfupdate(options.pf)
jsonfile = pf.get('json')
tempjson = pf.get('tempjson')
database = pf.get('database')
net_regex = pf.get('net_regex',defaultval=False)
sta_regex = pf.get('sta_regex',defaultval=False)

if (not json):
    print "Requerimos valor para [json] in pf: %s" % (options.pf)
    sys.exit()

if (not tempjson):
    print "Requerimos valor para [tempjson] in pf: %s" % (options.pf)
    sys.exit()

if (not database):
    print "Requerimos valor para [database] in pf: %s" % (options.pf)
    sys.exit()

def subset_table(db, subset):
    '''
    Tomar el db object y correr subset.
    Verificar output.
    '''

    db = db.subset(subset)

    if db.query('dbRECORD_COUNT') <1:
        sys.exit('\nNo hay origenes validos luego de subset.(%s)\n' % subset)

    return db

def join_table(db, table, outer=False, pattern1=None, pattern2=None):
    '''
    Tomar el db object y correr join.
    Verificar output.
    '''

    db = db.join(table, outer, pattern1, pattern2)

    if db.query('dbRECORD_COUNT') <1:
        sys.exit('\nNo hay origenes validos luego de join.(%s)\n' % table)

    return db


def buscar_estaciones(database):
    '''
    Subroutina para conectarse a Antelope y
    buscar la lista de estaciones validas.
    '''

    db = datascope.dbopen(database, 'r')
    db = db.lookup('', 'site', '', '') 

    if options.verbose:
        print '%s records' % db.record_count

    if db.record_count <1:
        sys.exit('\nNo hay estaciones en lookup.(site)\n')

    db = subset_table(db,'offdate == NULL')

    if options.verbose:
        print '%s records' % db.record_count

    if db.query('dbRECORD_COUNT') <1:
        sys.exit('\nNo hay estaciones luego de offdate ==  NULL.(site)\n')


    db = join_table(db,'snetsta',outer=True)

    if options.verbose:
        print '%s records' % db.record_count

    if net_regex:
        db = subset_table(db,'snet =~ /%s/' % net_regex)

    if sta_regex:
        db = subset_table(db,'sta =~ /%s/' % sta_regex)

    db = db.sort(['snet','sta'])

    grp_nrecs = db.record_count

    if options.verbose:
        print 'Tenemos (%s) estaciones en db' % grp_nrecs


    estaciones = {}

    for record in db.iter_record():
        """
        Go through each station
        """

        if options.verbose:
            print 'estacion (%s)' % record.record

        #db.record = record 
        [ondate, offdate, lat, lon, elev, staname, sta, snet ] = record.getv('ondate',
                'offdate','lat','lon','elev','staname','sta','snet')

        nombre = '%s_%s' % (sta,snet)

        estaciones[nombre] = { 'ondate':ondate, 'offdate':offdate, 'lat':lat, 'lon':lon,
                'elev':elev, 'staname':staname, 'sta':sta, 'snet':snet }

    return estaciones


eventos = buscar_estaciones(database)

with open(tempjson, 'w') as outfile:
  json.dump(eventos, outfile, ensure_ascii=False,
        sort_keys=True, indent=4, separators=(',',': '))

outfile.close()


if os.path.isfile(tempjson):
    md5_new = hashlib.md5(open(tempjson).read()).hexdigest()
else:
    md5_new = ''

if os.path.isfile(jsonfile):
    md5_old = hashlib.md5(open(jsonfile).read()).hexdigest()
else:
    md5_old = ''

if md5_new != md5_old:
    print " ****** Nuevo JSON %s *********" % jsonfile
    try:
        print "nuevo: %s %s" % (tempjson,md5_new)
        print "viejo: %s %s" % (jsonfile,md5_old)
        os.rename(tempjson, jsonfile)
    except Exception,e:
        print "Error con os.rename() [%s]" % e

else:
    if options.verbose:
        print "No hay cambios en el db."
