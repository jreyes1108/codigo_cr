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
        dest="pf", help="parametros", default='event2json.pf')
parser.add_option("-t", "--time", action="store",
        dest="time", help="ventana de tiempo", default=False)
parser.add_option("-v", "--verbose", action="store_true",
        dest="verbose", help="verbose output", default=False)
(options, args) = parser.parse_args()

pf = stock.pfupdate(options.pf)
json_output = pf.get('json_output')
temp_json_output = pf.get('temp_json_output')
database = pf.get('database')
pueblos = pf.get('pueblos')
timeZone = pf.get('timeZone')

if (not json_output):
    print "Requerimos valor para [json_output] in pf: %s" % (options.pf)
    sys.exit()

if (not temp_json_output):
    print "Requerimos valor para [temp_json_output] in pf: %s" % (options.pf)
    sys.exit()

if (not database):
    print "Requerimos valor para [database] in pf: %s" % (options.pf)
    sys.exit()

if ( not os.path.isfile(pueblos) ):
    print "Requerimos valor para [pueblos] in pf: %s => %s " % (options.pf,pueblos)
    sys.exit()

if (not timeZone):
    print "Requerimos valor para [timeZone] in pf: %s" % (options.pf)
    sys.exit()

def get_mags(db, evid, orid):
    '''
    Tomar el db object y correr subset.
    Obtener la mejor magnitud.
    '''

    temp = db.subset('evid == %s && orid == %s' % (evid, orid) )
    
    allmags = {}

    if options.verbose: print "%s mags for [%s,%s]" %  (temp.record_count,evid,orid)

    if temp.record_count < 1:
        return -1, '-', {}

    else:
        temp.sort('lddate')
        #temp.sort('uncertainty',reverse=True)
        for record in temp.iter_record():
            [magid, magnitude, magtype, auth, sdobs, uncertainty ] = record.getv('magid',
            'magnitude','magtype','auth','sdobs','uncertainty')
            allmags[record.record] = {'magnitude':magnitude, 'magtype':magtype, 'auth':auth,
                'magid':magid, 'sdobs':sdobs, 'uncertainty':uncertainty }

    temp.free()

    return magnitude, magtype, allmags

def subset_table(db, subset):
    '''
    Tomar el db object y correr subset.
    Verificar output.
    '''

    db = db.subset(subset)

    if db.record_count <1:
        sys.exit('\nNo hay origenes validos luego de subset.(%s)\n' % subset)

    return db

def join_table(db, table, outer=False, pattern1=None, pattern2=None):
    '''
    Tomar el db object y correr join.
    Verificar output.
    '''

    db = db.join(table, outer, pattern1, pattern2)

    if db.record_count <1:
        sys.exit('\nNo hay origenes validos luego de join.(%s)\n' % table)

    return db


def leer_pueblos(file):
    '''
    Subroutina para leer archivo de pueblos
    '''
    list = []
    with codecs.open(file,'r','latin-1') as f:
        for line in f:
            try:
                (pueblo,distrito,canton,provincia,lon,lat) = line.rstrip().split(',')
            except Exception,e:
                print "Problema al leer line en %s. %s" % (file,e)
                sys.exit()

            if options.verbose:
                print "%s -> %s -> %s -> %s [%s,%s]" % \
                    (pueblo,distrito,canton,provincia,lon,lat)

            if pueblo and distrito and canton and provincia and lat and lon:
                tempdict = {
                    'pueblo':pueblo.encode('ascii', 'xmlcharrefreplace'),
                    'distrito':distrito.encode('ascii', 'xmlcharrefreplace'),
                    'canton':canton.encode('ascii', 'xmlcharrefreplace'),
                    'provincia':provincia.encode('ascii', 'xmlcharrefreplace'),
                    'pueblolon':float(lon),
                    'pueblolat':float(lat)
                }

                list.append( tempdict )
            else:
                print "**** ERROR en %s. ****"
                sys.exit()

    f.close()

    return list

def haversine(lon1, lat1, lon2, lat2):
    """
    Calculate the great circle distance between two points 
    on the earth (specified in decimal degrees)
    http://stackoverflow.com/questions/15736995/how-can-i-quickly-estimate-the-distance-between-two-latitude-longitude-points
    """
    # convert decimal degrees to radians 
    lon1, lat1, lon2, lat2 = map(radians, [lon1, lat1, lon2, lat2])
    # haversine formula 
    dlon = lon2 - lon1 
    dlat = lat2 - lat1 
    a = sin(dlat/2)**2 + cos(lat1) * cos(lat2) * sin(dlon/2)**2
    c = 2 * asin(sqrt(a)) 
    km = 6367 * c
    return km


def buscar_pueblo(lat,lon):
    '''
    Subroutina para calcular distancia a la
    ciudad mas cercana.
    '''

    minDist = 99999999999
    lat = float(lat)
    lon = float(lon)
    

    for test in listado_pueblos:

        if options.verbose:
            print "coords.dist(%s,%s,%s,%s)" % (lat,lon,test['pueblolat'],test['pueblolon'])

        dist,az = coords.dist(test['pueblolat'],test['pueblolon'],lat,lon)

        if dist < minDist:
            templat = test['pueblolat']
            templon = test['pueblolon']
            final = test
            finalDist = dist
            finalAz = az
            minDist = dist

    finalDist = haversine(lon, lat, templon, templat)

    return (final,finalDist,finalAz)


def buscar_eventos(database):
    '''
    Subroutina para conectarse a Antelope y
    buscar la lista de eventos mas recientes.
    '''

    db = datascope.dbopen(database, 'r')
    db = db.lookup('', 'event', '', '') 

    netmag = db.lookup('', 'netmag', '', '') 
    netmag = join_table(netmag,'origerr', outer=True)

    db = subset_table(db,'evid != NULL')

    if db.record_count <1:
        sys.exit('\nNo hay eventos en lookup.(event)\n')

    db = join_table(db, 'origin')

    if options.time:
        db = subset_table(db,'time > (now() - %s)' % options.time)

    db = subset_table(db,'orid == prefor')

    #db = join_table(db,'netmag', outer=True, pattern1='mlid', pattern2='magid')

    #db = join_table(db,'origerr', outer=True)

    #db = subset_table(db,'mlid == magid')

    db = db.sort(['evid','orid'],unique=True)

    db = db.sort(['origin.time'],reverse=True)

    grp_nrecs = db.record_count

    if options.verbose:
        print 'Tenemos (%s) eventos en db' % grp_nrecs


    eventos = {}

    for record in db.iter_record():
        """
        Go through each event
        """

        if options.verbose:
            print 'Evento (%s)' % record.record

        [time, lat, lon, depth, orid, evid, review, nass, ndef, 
        auth] = record.getv('time','lat','lon','depth','orid','evid',
        'review','nass','ndef','auth')

        magnitude, magtype, allmags = get_mags(netmag,evid,orid)

        #db.record = record 
        #[time, lat, lon, depth, orid, evid, review, nass, ndef, magnitude, 
        #magtype, auth, sdobs ] = record.getv('time','lat','lon','depth','orid','evid',
        #'review','nass','ndef','magnitude','magtype','auth','sdobs')

        eventos[record.record] = { 'time':time, 'lat':lat, 'lon':lon, 'depth':depth,
                'orid':orid, 'evid':evid, 'review':review, 'nass':nass, 'allmags':allmags,
                'ndef':ndef, 'magnitude':magnitude, 'magtype':magtype, 'auth':auth }

        eventos[record.record]['horaLocal'] = stock.epoch2str(time,'%H:%M:%S',timeZone)
        eventos[record.record]['diaLocal'] = stock.epoch2str(time,'%Y-%m-%d',timeZone)
        eventos[record.record]['timeZone'] = timeZone
        [lugar,dist,az] = buscar_pueblo(lat,lon)
        eventos[record.record]['distancia'] = '%0.1f' % dist
        eventos[record.record]['acimut'] = '%0.1f' % az
        eventos[record.record].update(lugar)

        # Fix vals


        if eventos[record.record]['depth'] < 1:
            eventos[record.record]['depth'] = '-' 
        else:
            eventos[record.record]['depth'] = '%0.0f' % eventos[record.record]['depth']

        if eventos[record.record]['magnitude'] < 0: 
            eventos[record.record]['magnitude'] = '-'
        else:
            eventos[record.record]['magnitude'] = '%0.1f' % eventos[record.record]['magnitude']

        if options.verbose:
            pprint.pprint( eventos[record.record] )

    return eventos


listado_pueblos = leer_pueblos(pueblos)
eventos = buscar_eventos(database)

with open(temp_json_output, 'w') as outfile:
  json.dump(eventos, outfile, ensure_ascii=False,
        sort_keys=True, indent=4, separators=(',',': '))

outfile.close()


if os.path.isfile(temp_json_output):
    md5_new = hashlib.md5(open(temp_json_output).read()).hexdigest()
else:
    md5_new = ''

if os.path.isfile(json_output):
    md5_old = hashlib.md5(open(json_output).read()).hexdigest()
else:
    md5_old = ''

if md5_new != md5_old:
    print " ****** Nuevo JSON %s *********" % json_output
    try:
        print "nuevo: %s %s" % (temp_json_output,md5_new)
        print "viejo: %s %s" % (json_output,md5_old)
        os.rename(temp_json_output, json_output)
    except Exception,e:
        print "Error con os.rename() [%s]" % e

else:
    if options.verbose:
        print "nuevo: %s %s" % (temp_json_output,md5_new)
        print "viejo: %s %s" % (json_output,md5_old)
        print "No hay cambios en el db."
        os.remove(temp_json_output)
