var k8s = require('../k8s');

module.exports = function(firebase) {
    // Configuration
    var timeout = 1000 * 60 * 5;

    return function(job, done) {
        var data = job.data,
            log = firebase.child('logs').child(data.logId).child('children'),
            client = k8s.createClientFromCluster(data.cluster),

            raw = log.push({
                type: 'raw',
            }).child('children'),

            timeoutIdentifier = setTimeout(function(){
                console.log('[' + job.id + '] Destroying read stream after timeout');

                stream.destroy();
                finish();
            }, timeout),

            finish = function() {
                if (data.removeLog) {
                    console.log('[' + job.id + '] Removing log "' + data.logId + '"');

                    log.remove();
                }

                console.log('[' + job.id + '] Processed');
                clearTimeout(timeoutIdentifier);

                done();
            }
        ;

        console.log('[' + job.id + '] Processing', data);

        var stream = client.ns(data.namespace).po.log({name: data.pod, qs:{ follow: true } });
        stream.on('data', function(chunk) {
            raw.push({
                type: 'text',
                contents: chunk.toString(),
            });
        });

        stream.on('close', function() {
            console.log('[' + job.id + '] Stream was closed');

            finish();
        });

        stream.on('error', function(error) {
            console.log('[' + job.id + '] Stream was errored', error);

            finish();
        });

        stream.on('end', function(result) {
            console.log('[' + job.id + '] Stream was ended', result);

            finish();
        });
    };
};
