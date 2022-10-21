/**
 * Created by dongshufeng on 3/8 0008.
 */
/*! (C) 2014 Glyptodon LLC - glyptodon.org/MIT-LICENSE */
var Guacamole = Guacamole || {};
Guacamole.ArrayBufferReader = function (b) {
    var a = this;
    b.onblob = function (g) {
        var h = window.atob(g);
        var d = new ArrayBuffer(h.length);
        var f = new Uint8Array(d);
        for (var c = 0; c < h.length; c++) {
            f[c] = h.charCodeAt(c)
        }
        if (a.ondata) {
            a.ondata(d)
        }
    };
    b.onend = function () {
        if (a.onend) {
            a.onend()
        }
    };
    this.ondata = null;
    this.onend = null
};
var Guacamole = Guacamole || {};
Guacamole.ArrayBufferWriter = function (c) {
    var b = this;
    c.onack = function (d) {
        if (b.onack) {
            b.onack(d)
        }
    };
    function a(d) {
        var g = "";
        for (var f = 0; f < d.byteLength; f++) {
            g += String.fromCharCode(d[f])
        }
        c.sendBlob(window.btoa(g))
    }
    this.sendData = function (f) {
        var d = new Uint8Array(f);
        if (d.length <= 8064) {
            a(d)
        } else {
            for (var g = 0; g < d.length; g += 8064) {
                a(d.subarray(g, g + 8094))
            }
        }
    };
    this.sendEnd = function () {
        c.sendEnd()
    };
    this.onack = null
};
var Guacamole = Guacamole || {};
Guacamole.AudioChannel = function AudioChannel() {
    var c = this;
    var f = Guacamole.AudioChannel.getTimestamp();
    var b = f;
    this.sync = function a() {
        var h = Guacamole.AudioChannel.getTimestamp();
        var g = h - b;
        f = Math.min(f, h + g);
        b = h
    };
    this.play = function d(g, k, i) {
        var j = new Guacamole.AudioChannel.Packet(g, i);
        var h = Guacamole.AudioChannel.getTimestamp();
        if (f < h) {
            f = h
        }
        j.play(f);
        f += k
    }
};
if (window.AudioContext) {
    try {
        Guacamole.AudioChannel.context = new AudioContext()
    } catch (e) {}

} else {
    if (window.webkitAudioContext) {
        try {
            Guacamole.AudioChannel.context = new webkitAudioContext()
        } catch (e) {}

    }
}
Guacamole.AudioChannel.getTimestamp = function () {
    if (Guacamole.AudioChannel.context) {
        return Guacamole.AudioChannel.context.currentTime * 1000
    }
    if (window.performance) {
        if (window.performance.now) {
            return window.performance.now()
        }
        if (window.performance.webkitNow) {
            return window.performance.webkitNow()
        }
    }
    return new Date().getTime()
};
Guacamole.AudioChannel.Packet = function (m, h) {
    this.play = function (n) {};
    if (Guacamole.AudioChannel.context) {
        var l = null;
        var f = function (n) {
            l = n
        };
        var i = new FileReader();
        i.onload = function () {
            Guacamole.AudioChannel.context.decodeAudioData(i.result, function (n) {
                f(n)
            })
        };
        i.readAsArrayBuffer(h);
        var a = Guacamole.AudioChannel.context.createBufferSource();
        a.connect(Guacamole.AudioChannel.context.destination);
        if (!a.start) {
            a.start = a.noteOn
        }
        var d;
        function c(n) {
            a.buffer = n;
            a.start(d / 1000)
        }
        this.play = function (n) {
            d = n;
            if (l) {
                c(l)
            } else {
                f = c
            }
        }
    } else {
        var k = false;
        var g = null;
        try {
            g = new Audio()
        } catch (j) {}

        if (g) {
            var i = new FileReader();
            i.onload = function () {
                var p = "";
                var n = new Uint8Array(i.result);
                for (var o = 0; o < n.byteLength; o++) {
                    p += String.fromCharCode(n[o])
                }
                g.src = "data:" + m + ";base64," + window.btoa(p);
                if (k) {
                    g.play()
                }
            };
            i.readAsArrayBuffer(h);
            function b() {
                if (g.src) {
                    g.play()
                } else {
                    k = true
                }
            }
            this.play = function (n) {
                var p = Guacamole.AudioChannel.getTimestamp();
                var o = n - p;
                if (o < 0) {
                    b()
                } else {
                    window.setTimeout(b, o)
                }
            }
        }
    }
};
var Guacamole = Guacamole || {};
Guacamole.BlobReader = function (f, a) {
    var d = this;
    var c = 0;
    var b;
    if (window.BlobBuilder) {
        b = new BlobBuilder()
    } else {
        if (window.WebKitBlobBuilder) {
            b = new WebKitBlobBuilder()
        } else {
            if (window.MozBlobBuilder) {
                b = new MozBlobBuilder()
            } else {
                b = new(function () {
                    var g = [];
                    this.append = function (h) {
                        g.push(new Blob([h], {
                            type : a
                        }))
                    };
                    this.getBlob = function () {
                        return new Blob(g, {
                            type : a
                        })
                    }
                })()
            }
        }
    }
    f.onblob = function (k) {
        var l = window.atob(k);
        var h = new ArrayBuffer(l.length);
        var j = new Uint8Array(h);
        for (var g = 0; g < l.length; g++) {
            j[g] = l.charCodeAt(g)
        }
        b.append(h);
        c += h.byteLength;
        if (d.onprogress) {
            d.onprogress(h.byteLength)
        }
        f.sendAck("OK", 0)
    };
    f.onend = function () {
        if (d.onend) {
            d.onend()
        }
    };
    this.getLength = function () {
        return c
    };
    this.getBlob = function () {
        return b.getBlob()
    };
    this.onprogress = null;
    this.onend = null
};
var Guacamole = Guacamole || {};
Guacamole.Client = function (D) {
    var E = this;
    var g = 0;
    var v = 1;
    var i = 2;
    var h = 3;
    var a = 4;
    var j = 5;
    var H = g;
    var z = 0;
    var n = null;
    var G = {
        0 : "butt",
        1 : "round",
        2 : "square"
    };
    var k = {
        0 : "bevel",
        1 : "miter",
        2 : "round"
    };
    var A = new Guacamole.Display();
    var m = {};
    var r = {};
    var l = [];
    var u = [];
    var b = [];
    var f = new Guacamole.IntegerPool();
    var q = [];
    function x(I) {
        if (I != H) {
            H = I;
            if (E.onstatechange) {
                E.onstatechange(H)
            }
        }
    }
    function C() {
        return H == h || H == i
    }
    this.getDisplay = function () {
        return A
    };
    this.sendSize = function (J, I) {
        if (!1) {
            return
        }
        D.sendMessage("size", J, I)
    };
    this.sendKeyEvent = function (I, J) {
        if (!C()) {
            return
        }
        D.sendMessage("key", J, I)
    };
    this.sendMouseState = function (J) {
        if (!C()) {
            return
        }
        A.moveCursor(Math.floor(J.x), Math.floor(J.y));
        var I = 0;
        if (J.left) {
            I |= 1
        }
        if (J.middle) {
            I |= 2
        }
        if (J.right) {
            I |= 4
        }
        if (J.up) {
            I |= 8
        }
        if (J.down) {
            I |= 16
        }
        D.sendMessage("mouse", Math.floor(J.x), Math.floor(J.y), I)
    };
    this.setClipboard = function (K) {
        if (!C()) {
            return
        }
        var L = E.createClipboardStream("text/plain");
        var J = new Guacamole.StringWriter(L);
        for (var I = 0; I < K.length; I += 4096) {
            J.sendText(K.substring(I, I + 4096))
        }
        J.sendEnd()
    };
    this.createFileStream = function (I, J) {
        var K = f.next();
        D.sendMessage("file", K, I, J);
        var M = q[K] = new Guacamole.OutputStream(E, K);
        var L = M.sendEnd;
        M.sendEnd = function () {
            L();
            f.free(K);
            delete q[K]
        };
        return M
    };
    this.createPipeStream = function (I, K) {
        var J = f.next();
        D.sendMessage("pipe", J, I, K);
        var M = q[J] = new Guacamole.OutputStream(E, J);
        var L = M.sendEnd;
        M.sendEnd = function () {
            L();
            f.free(J);
            delete q[J]
        };
        return M
    };
    this.createClipboardStream = function (I) {
        var J = f.next();
        D.sendMessage("clipboard", J, I);
        var L = q[J] = new Guacamole.OutputStream(E, J);
        var K = L.sendEnd;
        L.sendEnd = function () {
            K();
            f.free(J);
            delete q[J]
        };
        return L
    };
    this.createObjectOutputStream = function y(K, I, J) {
        var O = f.next();
        D.sendMessage("put", K, O, I, J);
        var N = q[O] = new Guacamole.OutputStream(E, O);
        var L = N.sendEnd;
        N.sendEnd = function M() {
            L();
            f.free(O);
            delete q[O]
        };
        return N
    };
    this.requestObjectInputStream = function d(J, I) {
        if (!C()) {
            return
        }
        D.sendMessage("get", J, I)
    };
    this.sendAck = function (I, K, J) {
        if (!C()) {
            return
        }
        D.sendMessage("ack", I, K, J)
    };
    this.sendBlob = function (I, J) {
        if (!C()) {
            return
        }
        D.sendMessage("blob", I, J)
    };
    this.endStream = function (I) {
        if (!C()) {
            return
        }
        D.sendMessage("end", I)
    };
    this.onstatechange = null;
    this.onname = null;
    this.onerror = null;
    this.onclipboard = null;
    this.onfile = null;
    this.onfilesystem = null;
    this.onpipe = null;
    this.onsync = null;
    var s = function s(I) {
        var J = r[I];
        if (!J) {
            J = r[I] = new Guacamole.AudioChannel()
        }
        return J
    };
    function p(I) {
        var J = m[I];
        if (!J) {
            if (I === 0) {
                J = A.getDefaultLayer()
            } else {
                if (I > 0) {
                    J = A.createLayer()
                } else {
                    J = A.createBuffer()
                }
            }
            m[I] = J
        }
        return J
    }
    function c(I) {
        var J = l[I];
        if (J == null) {
            J = l[I] = new Guacamole.Parser();
            J.oninstruction = D.oninstruction
        }
        return J
    }
    var t = {
        "miter-limit" : function (I, J) {
            A.setMiterLimit(I, parseFloat(J))
        }
    };
    var o = {
        ack : function (J) {
            var M = parseInt(J[0]);
            var K = J[1];
            var I = parseInt(J[2]);
            var L = q[M];
            if (L) {
                if (L.onack) {
                    L.onack(new Guacamole.Status(I, K))
                }
                if (I >= 256) {
                    f.free(M);
                    delete q[M]
                }
            }
        },
        arc : function (O) {
            var N = p(parseInt(O[0]));
            var J = parseInt(O[1]);
            var P = parseInt(O[2]);
            var I = parseInt(O[3]);
            var M = parseFloat(O[4]);
            var K = parseFloat(O[5]);
            var L = parseInt(O[6]);
            A.arc(N, J, P, I, M, K, L != 0)
        },
        audio : function (K) {
            var N = parseInt(K[0]);
            var J = s(parseInt(K[1]));
            var I = K[2];
            var L = parseFloat(K[3]);
            var M = u[N] = new Guacamole.InputStream(E, N);
            var O = new Guacamole.BlobReader(M, I);
            O.onend = function () {
                J.play(I, L, O.getBlob())
            };
            E.sendAck(N, "OK", 0)
        },
        blob : function (I) {
            var L = parseInt(I[0]);
            var J = I[1];
            var K = u[L];
             if (K && K.onblob)
                K.onblob(J);

        },
        body : function F(L) {
            var M = parseInt(L[0]);
            var K = b[M];
            var O = parseInt(L[1]);
            var I = L[2];
            var J = L[3];
            if (K && K.onbody) {
                var N = u[O] = new Guacamole.InputStream(E, O);
                K.onbody(N, I, J)
            } else {
                E.sendAck(O, "Receipt of body unsupported", 256)
            }
        },
        cfill : function (N) {
            var O = parseInt(N[0]);
            var K = p(parseInt(N[1]));
            var M = parseInt(N[2]);
            var L = parseInt(N[3]);
            var I = parseInt(N[4]);
            var J = parseInt(N[5]);
            A.setChannelMask(K, O);
            A.fillColor(K, M, L, I, J)
        },
        clip : function (J) {
            var I = p(parseInt(J[0]));
            A.clip(I)
        },
        clipboard : function (J) {
            var L = parseInt(J[0]);
            var I = J[1];
            if (E.onclipboard) {
                var K = u[L] = new Guacamole.InputStream(E, L);
                E.onclipboard(K, I)
            } else {
                E.sendAck(L, "Clipboard unsupported", 256)
            }
        },
        close : function (J) {
            var I = p(parseInt(J[0]));
            A.close(I)
        },
        copy : function (Q) {
            var I = p(parseInt(Q[0]));
            var M = parseInt(Q[1]);
            var L = parseInt(Q[2]);
            var K = parseInt(Q[3]);
            var R = parseInt(Q[4]);
            var P = parseInt(Q[5]);
            var J = p(parseInt(Q[6]));
            var O = parseInt(Q[7]);
            var N = parseInt(Q[8]);
            A.setChannelMask(J, P);
            A.copy(I, M, L, K, R, J, O, N)
        },
        cstroke : function (Q) {
            var N = parseInt(Q[0]);
            var L = p(parseInt(Q[1]));
            var R = G[parseInt(Q[2])];
            var J = k[parseInt(Q[3])];
            var P = parseInt(Q[4]);
            var I = parseInt(Q[5]);
            var K = parseInt(Q[6]);
            var M = parseInt(Q[7]);
            var O = parseInt(Q[8]);
            A.setChannelMask(L, N);
            A.strokeColor(L, R, J, P, I, K, M, O)
        },
        cursor : function (P) {
            var O = parseInt(P[0]);
            var N = parseInt(P[1]);
            var L = p(parseInt(P[2]));
            var J = parseInt(P[3]);
            var I = parseInt(P[4]);
            var M = parseInt(P[5]);
            var K = parseInt(P[6]);
            A.setCursor(O, N, L, J, I, M, K)
        },
        curve : function (O) {
            var N = p(parseInt(O[0]));
            var K = parseInt(O[1]);
            var J = parseInt(O[2]);
            var M = parseInt(O[3]);
            var L = parseInt(O[4]);
            var I = parseInt(O[5]);
            var P = parseInt(O[6]);
            A.curveTo(N, K, J, M, L, I, P)
        },
        dispose : function (K) {
            var I = parseInt(K[0]);
            if (I > 0) {
                var J = p(I);
                J.dispose();
                delete m[I]
            } else {
                if (I < 0) {
                    delete m[I]
                }
            }
        },
        distort : function (Q) {
            var I = parseInt(Q[0]);
            var P = parseFloat(Q[1]);
            var O = parseFloat(Q[2]);
            var N = parseFloat(Q[3]);
            var M = parseFloat(Q[4]);
            var L = parseFloat(Q[5]);
            var K = parseFloat(Q[6]);
            if (I >= 0) {
                var J = p(I);
                J.distort(P, O, N, M, L, K)
            }
        },
        error : function (J) {
            var K = J[0];
            var I = parseInt(J[1]);
            if (E.onerror) {
                E.onerror(new Guacamole.Status(I, K))
            }
            E.disconnect()
        },
        end : function (I) {
            var K = parseInt(I[0]);
            var J = u[K];
            if(J){
                if(J.onend) {
                    J.onend();
                    delete u[K];
                }
            }

        },
        file : function (K) {
            var M = parseInt(K[0]);
            var I = K[1];
            var J = K[2];
            if (E.onfile) {
                var L = u[M] = new Guacamole.InputStream(E, M);
                E.onfile(L, I, J)
            } else {
                E.sendAck(M, "File transfer unsupported", 256)
            }
        },
        filesystem : function w(K) {
            var L = parseInt(K[0]);
            var J = K[1];
            if (E.onfilesystem) {
                var I = b[L] = new Guacamole.Object(E, L);
                E.onfilesystem(I, J)
            }
        },
        identity : function (J) {
            var I = p(parseInt(J[0]));
            A.setTransform(I, 1, 0, 0, 1, 0, 0)
        },
        img : function (R) {
            var I = parseInt(R[0]);
            var M = parseInt(R[1]);
            var L = p(parseInt(R[2]));
            var O = R[3];
            var P = parseInt(R[4]);
            var N = parseInt(R[5]);
            var Q = u[I] = new Guacamole.InputStream(E, I);
            var K = new Guacamole.DataURIReader(Q, O);
            K.onend = function J() {
                A.setChannelMask(L, M);
                A.draw(L, P, N, K.getURI())
            }
        },
        jpeg : function (K) {
            var N = parseInt(K[0]);
            var J = p(parseInt(K[1]));
            var I = parseInt(K[2]);
            var M = parseInt(K[3]);
            var L = K[4];
            A.setChannelMask(J, N);
            A.draw(J, I, M, "data:image/jpeg;base64," + L)
        },
        lfill : function (K) {
            var L = parseInt(K[0]);
            var J = p(parseInt(K[1]));
            var I = p(parseInt(K[2]));
            A.setChannelMask(J, L);
            A.fillLayer(J, I)
        },
        line : function (K) {
            var J = p(parseInt(K[0]));
            var I = parseInt(K[1]);
            var L = parseInt(K[2]);
            A.lineTo(J, I, L)
        },
        lstroke : function (K) {
            var L = parseInt(K[0]);
            var J = p(parseInt(K[1]));
            var I = p(parseInt(K[2]));
            A.setChannelMask(J, L);
            A.strokeLayer(J, I)
        },
        move : function (N) {
            var J = parseInt(N[0]);
            var K = parseInt(N[1]);
            var I = parseInt(N[2]);
            var P = parseInt(N[3]);
            var O = parseInt(N[4]);
            if (J > 0 && K >= 0) {
                var L = p(J);
                var M = p(K);
                L.move(M, I, P, O)
            }
        },
        name : function (I) {
            if (E.onname) {
                E.onname(I[0])
            }
        },
        nest : function (I) {
            var J = c(parseInt(I[0]));
            J.receive(I[1])
        },
        pipe : function (K) {
            var M = parseInt(K[0]);
            var I = K[1];
            var J = K[2];
            if (E.onpipe) {
                var L = u[M] = new Guacamole.InputStream(E, M);
                E.onpipe(L, I, J)
            } else {
                E.sendAck(M, "Named pipes unsupported", 256)
            }
        },
        png : function (K) {
            var N = parseInt(K[0]);
            var J = p(parseInt(K[1]));
            var I = parseInt(K[2]);
            var M = parseInt(K[3]);
            var L = K[4];
            A.setChannelMask(J, N);
            A.draw(J, I, M, "data:image/png;base64," + L)
        },
        pop : function (J) {
            var I = p(parseInt(J[0]));
            A.pop(I)
        },
        push : function (J) {
            var I = p(parseInt(J[0]));
            A.push(I)
        },
        rect : function (M) {
            var K = p(parseInt(M[0]));
            var I = parseInt(M[1]);
            var N = parseInt(M[2]);
            var J = parseInt(M[3]);
            var L = parseInt(M[4]);
            A.rect(K, I, N, J, L)
        },
        reset : function (J) {
            var I = p(parseInt(J[0]));
            A.reset(I)
        },
        set : function (L) {
            var J = p(parseInt(L[0]));
            var I = L[1];
            var M = L[2];
            var K = t[I];
            if (K) {
                K(J, M)
            }
        },
        shade : function (L) {
            var I = parseInt(L[0]);
            var J = parseInt(L[1]);
            if (I >= 0) {
                var K = p(I);
                K.shade(J)
            }
        },
        size : function (M) {
            var J = parseInt(M[0]);
            var K = p(J);
            var L = parseInt(M[1]);
            var I = parseInt(M[2]);
            A.resize(K, L, I)
        },
        start : function (K) {
            var J = p(parseInt(K[0]));
            var I = parseInt(K[1]);
            var L = parseInt(K[2]);
            A.moveTo(J, I, L)
        },
        sync : function (J) {
            var K = parseInt(J[0]);
            A.flush(function I() {
                for (var M in r) {
                    var L = r[M];
                    if (L) {
                        L.sync()
                    }
                }
                if (K !== z) {
                    D.sendMessage("sync", K);
                    z = K
                }
            });
            if (H === i) {
                x(h)
            }
            if (E.onsync) {
                E.onsync(K)
            }
        },
        transfer : function (Q) {
            var I = p(parseInt(Q[0]));
            var N = parseInt(Q[1]);
            var M = parseInt(Q[2]);
            var L = parseInt(Q[3]);
            var R = parseInt(Q[4]);
            var K = parseInt(Q[5]);
            var J = p(parseInt(Q[6]));
            var P = parseInt(Q[7]);
            var O = parseInt(Q[8]);
            if (K === 3) {
                A.put(I, N, M, L, R, J, P, O)
            } else {
                if (K !== 5) {
                    A.transfer(I, N, M, L, R, J, P, O, Guacamole.Client.DefaultTransferFunction[K])
                }
            }
        },
        transform : function (L) {
            var K = p(parseInt(L[0]));
            var J = parseFloat(L[1]);
            var I = parseFloat(L[2]);
            var P = parseFloat(L[3]);
            var O = parseFloat(L[4]);
            var N = parseFloat(L[5]);
            var M = parseFloat(L[6]);
            A.transform(K, J, I, P, O, N, M)
        },
        undefine : function B(J) {
            var K = parseInt(J[0]);
            var I = b[K];
            if (I && I.onundefine) {
                I.onundefine()
            }
        },
        video : function (K) {
            var N = parseInt(K[0]);
            var J = p(parseInt(K[1]));
            var I = K[2];
            var L = parseFloat(K[3]);
            var M = u[N] = new Guacamole.InputStream(E, N);
            var O = new Guacamole.BlobReader(M, I);
            O.onend = function () {
                var P = new FileReader();
                P.onload = function () {
                    var S = "";
                    var Q = new Uint8Array(P.result);
                    for (var R = 0; R < Q.byteLength; R++) {
                        S += String.fromCharCode(Q[R])
                    }
                    J.play(I, L, "data:" + I + ";base64," + window.btoa(S))
                };
                P.readAsArrayBuffer(O.getBlob())
            };
            D.sendMessage("ack", N, "OK", 0)
        }
    };
    D.oninstruction = function (K, J) {
        var I = o[K];
        if (I) {
            I(J)
        }
    };
    this.disconnect = function () {
        if (H != j && H != a) {
            x(a);
            if (n) {
                window.clearInterval(n)
            }
            D.sendMessage("disconnect");
            D.disconnect();
            x(j)
        }
    };
    this.connect = function (J) {
        x(v);
        try {
            D.connect(J)
        } catch (I) {
            x(g);
            throw I
        }
        n = window.setInterval(function () {
            D.sendMessage("sync", z)
        }, 5000);
        x(i)
    }
};
Guacamole.Client.DefaultTransferFunction = {
    0 : function (a, b) {
        b.red = b.green = b.blue = 0
    },
    15 : function (a, b) {
        b.red = b.green = b.blue = 255
    },
    3 : function (a, b) {
        b.red = a.red;
        b.green = a.green;
        b.blue = a.blue;
        b.alpha = a.alpha
    },
    5 : function (a, b) {},
    12 : function (a, b) {
        b.red = 255 & ~a.red;
        b.green = 255 & ~a.green;
        b.blue = 255 & ~a.blue;
        b.alpha = a.alpha
    },
    10 : function (a, b) {
        b.red = 255 & ~b.red;
        b.green = 255 & ~b.green;
        b.blue = 255 & ~b.blue
    },
    1 : function (a, b) {
        b.red = (a.red & b.red);
        b.green = (a.green & b.green);
        b.blue = (a.blue & b.blue)
    },
    14 : function (a, b) {
        b.red = 255 & ~(a.red & b.red);
        b.green = 255 & ~(a.green & b.green);
        b.blue = 255 & ~(a.blue & b.blue)
    },
    7 : function (a, b) {
        b.red = (a.red | b.red);
        b.green = (a.green | b.green);
        b.blue = (a.blue | b.blue)
    },
    8 : function (a, b) {
        b.red = 255 & ~(a.red | b.red);
        b.green = 255 & ~(a.green | b.green);
        b.blue = 255 & ~(a.blue | b.blue)
    },
    6 : function (a, b) {
        b.red = (a.red^b.red);
        b.green = (a.green^b.green);
        b.blue = (a.blue^b.blue)
    },
    9 : function (a, b) {
        b.red = 255 & ~(a.red^b.red);
        b.green = 255 & ~(a.green^b.green);
        b.blue = 255 & ~(a.blue^b.blue)
    },
    4 : function (a, b) {
        b.red = 255 & (~a.red & b.red);
        b.green = 255 & (~a.green & b.green);
        b.blue = 255 & (~a.blue & b.blue)
    },
    13 : function (a, b) {
        b.red = 255 & (~a.red | b.red);
        b.green = 255 & (~a.green | b.green);
        b.blue = 255 & (~a.blue | b.blue)
    },
    2 : function (a, b) {
        b.red = 255 & (a.red & ~b.red);
        b.green = 255 & (a.green & ~b.green);
        b.blue = 255 & (a.blue & ~b.blue)
    },
    11 : function (a, b) {
        b.red = 255 & (a.red | ~b.red);
        b.green = 255 & (a.green | ~b.green);
        b.blue = 255 & (a.blue | ~b.blue)
    }
};
var Guacamole = Guacamole || {};
Guacamole.DataURIReader = function (h, b) {
    var g = this;
    var f = "data:" + b + ";base64,";
    h.onblob = function a(i) {
        f += i
    };
    h.onend = function c() {
        if (g.onend) {
            g.onend()
        }
    };
    this.getURI = function d() {
        return f
    };
    this.onend = null
};
var Guacamole = Guacamole || {};
Guacamole.Display = function () {
    var l = this;
    var o = 0;
    var c = 1000;
    var b = 1;
    var i = document.createElement("div");
    i.style.position = "relative";
    i.style.width = o + "px";
    i.style.height = c + "px";
    i.style.transformOrigin = i.style.webkitTransformOrigin = i.style.MozTransformOrigin = i.style.OTransformOrigin = i.style.msTransformOrigin = "0 0";
    var g = new Guacamole.Display.VisibleLayer(o, c);
    var m = new Guacamole.Display.VisibleLayer(0, 0);
    m.setChannelMask(Guacamole.Layer.SRC);
    i.appendChild(g.getElement());
    i.appendChild(m.getElement());
    var a = document.createElement("div");
    a.style.position = "relative";
    a.style.width = (o * b) + "px";
    a.style.height = (c * b) + "px";
    a.appendChild(i);
    this.cursorHotspotX = 0;
    this.cursorHotspotY = 0;
    this.cursorX = 0;
    this.cursorY = 0;
    this.onresize = null;
    this.oncursor = null;
    var d = [];
    var h = [];
    function k() {
        var p = 0;
        while (p < h.length) {
            var q = h[p];
            if (!q.isReady()) {
                break
            }
            q.flush();
            p++
        }
        h.splice(0, p)
    }
    function n(q, p) {
        this.isReady = function () {
            for (var r = 0; r < p.length; r++) {
                if (p[r].blocked) {
                    return false
                }
            }
            return true
        };
        this.flush = function () {
            for (var r = 0; r < p.length; r++) {
                p[r].execute()
            }
            if (q) {
                q()
            }
        }
    }
    function f(r, q) {
        var p = this;
        this.blocked = q;
        this.unblock = function () {
            if (p.blocked) {
                p.blocked = false;
                k()
            }
        };
        this.execute = function () {
            if (r) {
                r()
            }
        }
    }
    function j(r, q) {
        var p = new f(r, q);
        d.push(p);
        return p
    }
    this.getElement = function () {
        return a
    };
    this.getWidth = function () {
        return o
    };
    this.getHeight = function () {
        return c
    };
    this.getDefaultLayer = function () {
        return g
    };
    this.getCursorLayer = function () {
        return m
    };
    this.createLayer = function () {
        var p = new Guacamole.Display.VisibleLayer(o, c);
        p.move(g, 0, 0, 0);
        return p
    };
    this.createBuffer = function () {
        var p = new Guacamole.Layer(0, 0);
        p.autosize = 1;
        return p
    };
    this.flush = function (p) {
        h.push(new n(p, d));
        d = [];
        k()
    };
    this.setCursor = function (v, t, u, q, p, s, w) {
        j(function r() {
            l.cursorHotspotX = v;
            l.cursorHotspotY = t;
            m.resize(s, w);
            m.copy(u, q, p, s, w, 0, 0);
            l.moveCursor(l.cursorX, l.cursorY);
            if (l.oncursor) {
                l.oncursor(m.getCanvas(), v, t)
            }
        })
    };
    this.showCursor = function (r) {
        var p = m.getElement();
        var q = p.parentNode;
        if (r === false) {
            if (q) {
                q.removeChild(p)
            }
        } else {
            if (q !== i) {
                i.appendChild(p)
            }
        }
    };
    this.moveCursor = function (p, q) {
        m.translate(p - l.cursorHotspotX, q - l.cursorHotspotY);
        l.cursorX = p;
        l.cursorY = q
    };
    this.resize = function (q, r, p) {
        j(function s() {
            q.resize(r, p);
            if (q === g) {
                o = r;
                c = p;
                i.style.width = o + "px";
                i.style.height = c + "px";
                a.style.width = (o * b) + "px";
                a.style.height = (c * b) + "px";
                if (l.onresize) {
                    l.onresize(r, p)
                }
            }
        })
    };
    this.drawImage = function (r, p, t, s) {
        j(function q() {
            r.drawImage(p, t, s)
        })
    };
    this.drawBlob = function (u, q, w, s) {
        var t = URL.createObjectURL(s);
        var r = j(function p() {
            u.drawImage(q, w, v);
            URL.revokeObjectURL(t)
        }, true);
        var v = new Image();
        v.onload = r.unblock;
        v.src = t
    };
    this.draw = function (t, p, v, s) {
        var r = j(function q() {
            t.drawImage(p, v, u)
        }, true);
        var u = new Image();
        u.onload = r.unblock;
        u.src = s
    };
    this.play = function (r, p, t, q) {
        var s = document.createElement("video");
        s.type = p;
        s.src = q;
        s.addEventListener("play", function () {
            function u() {
                r.drawImage(0, 0, s);
                if (!s.ended) {
                    window.setTimeout(u, 20)
                }
            }
            u()
        }, false);
        j(s.play)
    };
    this.transfer = function (v, q, p, r, A, t, w, u, s) {
        j(function z() {
            t.transfer(v, q, p, r, A, w, u, s)
        })
    };
    this.put = function (v, q, p, r, z, t, w, u) {
        j(function s() {
            t.put(v, q, p, r, z, w, u)
        })
    };
    this.copy = function (v, q, p, r, z, s, w, u) {
        j(function t() {
            s.copy(v, q, p, r, z, w, u)
        })
    };
    this.moveTo = function (r, p, s) {
        j(function q() {
            r.moveTo(p, s)
        })
    };
    this.lineTo = function (r, p, s) {
        j(function q() {
            r.lineTo(p, s)
        })
    };
    this.arc = function (v, q, w, p, u, r, t) {
        j(function s() {
            v.arc(q, w, p, u, r, t)
        })
    };
    this.curveTo = function (u, r, q, t, s, p, w) {
        j(function v() {
            u.curveTo(r, q, t, s, p, w)
        })
    };
    this.close = function (p) {
        j(function q() {
            p.close()
        })
    };
    this.rect = function (r, p, u, q, s) {
        j(function t() {
            r.rect(p, u, q, s)
        })
    };
    this.clip = function (p) {
        j(function q() {
            p.clip()
        })
    };
    this.strokeColor = function (t, x, q, v, p, s, u, w) {
        j(function y() {
            t.strokeColor(x, q, v, p, s, u, w)
        })
    };
    this.fillColor = function (s, u, t, p, q) {
        j(function v() {
            s.fillColor(u, t, p, q)
        })
    };
    this.strokeLayer = function (t, s, u, r, q) {
        j(function p() {
            t.strokeLayer(s, u, r, q)
        })
    };
    this.fillLayer = function (q, p) {
        j(function r() {
            q.fillLayer(p)
        })
    };
    this.push = function (q) {
        j(function p() {
            q.push()
        })
    };
    this.pop = function (q) {
        j(function p() {
            q.pop()
        })
    };
    this.reset = function (p) {
        j(function q() {
            p.reset()
        })
    };
    this.setTransform = function (r, q, p, w, u, t, s) {
        j(function v() {
            r.setTransform(q, p, w, u, t, s)
        })
    };
    this.transform = function (s, r, p, w, v, u, t) {
        j(function q() {
            s.transform(r, p, w, v, u, t)
        })
    };
    this.setChannelMask = function (q, p) {
        j(function r() {
            q.setChannelMask(p)
        })
    };
    this.setMiterLimit = function (r, p) {
        j(function q() {
            r.setMiterLimit(p)
        })
    };
    this.scale = function (p) {
        i.style.transform = i.style.WebkitTransform = i.style.MozTransform = i.style.OTransform = i.style.msTransform = "scale(" + p + "," + p + ")";
        b = p;
        a.style.width = (o * b) + "px";
        a.style.height = (c * b) + "px"
    };
    this.getScale = function () {
        return b
    };
    this.flatten = function () {
        var q = document.createElement("canvas");
        q.width = g.width;
        q.height = g.height;
        var r = q.getContext("2d");
        function p(w) {
            var v = [];
            for (var u in w.children) {
                v.push(w.children[u])
            }
            v.sort(function t(A, z) {
                var B = A.z - z.z;
                if (B !== 0) {
                    return B
                }
                var y = A.getElement();
                var C = z.getElement();
                var x = C.compareDocumentPosition(y);
                if (x & Node.DOCUMENT_POSITION_PRECEDING) {
                    return -1
                }
                if (x & Node.DOCUMENT_POSITION_FOLLOWING) {
                    return 1
                }
                return 0
            });
            return v
        }
        function s(z, u, B) {
            if (z.width > 0 && z.height > 0) {
                var t = r.globalAlpha;
                r.globalAlpha *= z.alpha / 255;
                r.drawImage(z.getCanvas(), u, B);
                var w = p(z);
                for (var v = 0; v < w.length; v++) {
                    var A = w[v];
                    s(A, u + A.x, B + A.y)
                }
                r.globalAlpha = t
            }
        }
        s(g, 0, 0);
        return q
    }
};
Guacamole.Display.VisibleLayer = function (g, a) {
    Guacamole.Layer.apply(this, [g, a]);
    var f = this;
    this.__unique_id = Guacamole.Display.VisibleLayer.__next_id++;
    this.alpha = 255;
    this.x = 0;
    this.y = 0;
    this.z = 0;
    this.matrix = [1, 0, 0, 1, 0, 0];
    this.parent = null;
    this.children = {};
    var d = f.getCanvas();
    d.style.position = "absolute";
    d.style.left = "0px";
    d.style.top = "0px";
    var i = document.createElement("div");
    i.appendChild(d);
    i.style.width = g + "px";
    i.style.height = a + "px";
    i.style.position = "absolute";
    i.style.left = "0px";
    i.style.top = "0px";
    i.style.overflow = "hidden";
    var c = this.resize;
    this.resize = function (k, j) {
        i.style.width = k + "px";
        i.style.height = j + "px";
        c(k, j)
    };
    this.getElement = function () {
        return i
    };
    var h = "translate(0px, 0px)";
    var b = "matrix(1, 0, 0, 1, 0, 0)";
    this.translate = function (j, k) {
        f.x = j;
        f.y = k;
        h = "translate(" + j + "px," + k + "px)";
        i.style.transform = i.style.WebkitTransform = i.style.MozTransform = i.style.OTransform = i.style.msTransform = h + " " + b
    };
    this.move = function (k, j, n, m) {
        if (f.parent !== k) {
            if (f.parent) {
                delete f.parent.children[f.__unique_id]
            }
            f.parent = k;
            k.children[f.__unique_id] = f;
            var l = k.getElement();
            l.appendChild(i)
        }
        f.translate(j, n);
        f.z = m;
        i.style.zIndex = m
    };
    this.shade = function (j) {
        f.alpha = j;
        i.style.opacity = j / 255
    };
    this.dispose = function () {
        if (f.parent) {
            delete f.parent.children[f.__unique_id];
            f.parent = null
        }
        if (i.parentNode) {
            i.parentNode.removeChild(i)
        }
    };
    this.distort = function (k, j, o, n, m, l) {
        f.matrix = [k, j, o, n, m, l];
        b = "matrix(" + k + "," + j + "," + o + "," + n + "," + m + "," + l + ")";
        i.style.transform = i.style.WebkitTransform = i.style.MozTransform = i.style.OTransform = i.style.msTransform = h + " " + b
    }
};
Guacamole.Display.VisibleLayer.__next_id = 0;
var Guacamole = Guacamole || {};
Guacamole.InputStream = function (a, b) {
    var c = this;
    this.index = b;
    this.onblob = null;
    this.onend = null;
    this.sendAck = function (f, d) {
        a.sendAck(c.index, f, d)
    }
};
var Guacamole = Guacamole || {};
Guacamole.IntegerPool = function () {
    var b = this;
    var a = [];
    this.next_int = 0;
    this.next = function () {
        if (a.length > 0) {
            return a.shift()
        }
        return b.next_int++
    };
    this.free = function (c) {
        a.push(c)
    }
};
var Guacamole = Guacamole || {};
Guacamole.JSONReader = function guacamoleJSONReader(h) {
    var a = this;
    var g = new Guacamole.StringReader(h);
    var c = "";
    this.getLength = function b() {
        return c.length
    };
    this.getJSON = function d() {
        return JSON.parse(c)
    };
    g.ontext = function i(j) {
        c += j;
        if (a.onprogress) {
            a.onprogress(j.length)
        }
    };
    g.onend = function f() {
        if (a.onend) {
            a.onend()
        }
    };
    this.onprogress = null;
    this.onend = null
};
var Guacamole = Guacamole || {};
Guacamole.Keyboard = function (c) {
    var j = this;
    this.onkeydown = null;
    this.onkeyup = null;
    var v = function () {
        var A = this;
        this.timestamp = new Date().getTime();
        this.defaultPrevented = false;
        this.keysym = null;
        this.reliable = false;
        this.getAge = function () {
            return new Date().getTime() - A.timestamp
        }
    };
    var w = function (F, E, C, A) {
        v.apply(this);
        this.keyCode = F;
        this.keyIdentifier = E;
        this.key = C;
        this.location = A;
        this.keysym = y(C, A) || i(F, A);
        if (this.keysym) {
            this.reliable = true
        }
        if (!this.keysym && f(F, E)) {
            this.keysym = y(E, A, j.modifiers.shift)
        }
        var B = !j.modifiers.ctrl && !(navigator && navigator.platform && navigator.platform.match(/^mac/i));
        var D = !j.modifiers.alt;
        if ((D && j.modifiers.ctrl) || (B && j.modifiers.alt) || j.modifiers.meta || j.modifiers.hyper) {
            this.reliable = true
        }
        l[F] = this.keysym
    };
    w.prototype = new v();
    var b = function (A) {
        v.apply(this);
        this.charCode = A;
        this.keysym = r(A);
        this.reliable = true
    };
    b.prototype = new v();
    var k = function (D, C, B, A) {
        v.apply(this);
        this.keyCode = D;
        this.keyIdentifier = C;
        this.key = B;
        this.location = A;
        this.keysym = l[D] || i(D, A) || y(B, A);
        this.reliable = true
    };
    k.prototype = new v();
    var z = [];
    var u = {
        8 : [65288],
        9 : [65289],
        13 : [65293],
        16 : [65505, 65505, 65506],
        17 : [65507, 65507, 65508],
        18 : [65513, 65513, 65027],
        19 : [65299],
        20 : [65509],
        27 : [65307],
        32 : [32],
        33 : [65365],
        34 : [65366],
        35 : [65367],
        36 : [65360],
        37 : [65361],
        38 : [65362],
        39 : [65363],
        40 : [65364],
        45 : [65379],
        46 : [65535],
        91 : [65515],
        92 : [65383],
        93 : null,
        112 : [65470],
        113 : [65471],
        114 : [65472],
        115 : [65473],
        116 : [65474],
        117 : [65475],
        118 : [65476],
        119 : [65477],
        120 : [65478],
        121 : [65479],
        122 : [65480],
        123 : [65481],
        144 : [65407],
        145 : [65300],
        225 : [65027]
    };
    var g = {
        Again : [65382],
        AllCandidates : [65341],
        Alphanumeric : [65328],
        Alt : [65513, 65513, 65027],
        Attn : [64782],
        AltGraph : [65027],
        ArrowDown : [65364],
        ArrowLeft : [65361],
        ArrowRight : [65363],
        ArrowUp : [65362],
        Backspace : [65288],
        CapsLock : [65509],
        Cancel : [65385],
        Clear : [65291],
        Convert : [65313],
        Copy : [64789],
        Crsel : [64796],
        CrSel : [64796],
        CodeInput : [65335],
        Compose : [65312],
        Control : [65507, 65507, 65508],
        ContextMenu : [65383],
        DeadGrave : [65104],
        DeadAcute : [65105],
        DeadCircumflex : [65106],
        DeadTilde : [65107],
        DeadMacron : [65108],
        DeadBreve : [65109],
        DeadAboveDot : [65110],
        DeadUmlaut : [65111],
        DeadAboveRing : [65112],
        DeadDoubleacute : [65113],
        DeadCaron : [65114],
        DeadCedilla : [65115],
        DeadOgonek : [65116],
        DeadIota : [65117],
        DeadVoicedSound : [65118],
        DeadSemivoicedSound : [65119],
        Delete : [65535],
        Down : [65364],
        End : [65367],
        Enter : [65293],
        EraseEof : [64774],
        Escape : [65307],
        Execute : [65378],
        Exsel : [64797],
        ExSel : [64797],
        F1 : [65470],
        F2 : [65471],
        F3 : [65472],
        F4 : [65473],
        F5 : [65474],
        F6 : [65475],
        F7 : [65476],
        F8 : [65477],
        F9 : [65478],
        F10 : [65479],
        F11 : [65480],
        F12 : [65481],
        F13 : [65482],
        F14 : [65483],
        F15 : [65484],
        F16 : [65485],
        F17 : [65486],
        F18 : [65487],
        F19 : [65488],
        F20 : [65489],
        F21 : [65490],
        F22 : [65491],
        F23 : [65492],
        F24 : [65493],
        Find : [65384],
        GroupFirst : [65036],
        GroupLast : [65038],
        GroupNext : [65032],
        GroupPrevious : [65034],
        FullWidth : null,
        HalfWidth : null,
        HangulMode : [65329],
        Hankaku : [65321],
        HanjaMode : [65332],
        Help : [65386],
        Hiragana : [65317],
        HiraganaKatakana : [65319],
        Home : [65360],
        Hyper : [65517, 65517, 65518],
        Insert : [65379],
        JapaneseHiragana : [65317],
        JapaneseKatakana : [65318],
        JapaneseRomaji : [65316],
        JunjaMode : [65336],
        KanaMode : [65325],
        KanjiMode : [65313],
        Katakana : [65318],
        Left : [65361],
        Meta : [65511, 65511, 65512],
        ModeChange : [65406],
        NumLock : [65407],
        PageDown : [65366],
        PageUp : [65365],
        Pause : [65299],
        Play : [64790],
        PreviousCandidate : [65342],
        PrintScreen : [64797],
        Redo : [65382],
        Right : [65363],
        RomanCharacters : null,
        Scroll : [65300],
        Select : [65376],
        Separator : [65452],
        Shift : [65505, 65505, 65506],
        SingleCandidate : [65340],
        Super : [65515, 65515, 65516],
        Tab : [65289],
        Up : [65362],
        Undo : [65381],
        Win : [65515],
        Zenkaku : [65320],
        ZenkakuHankaku : [65322]
    };
    var s = {
        65027 : true,
        65505 : true,
        65506 : true,
        65507 : true,
        65508 : true,
        65511 : true,
        65512 : true,
        65513 : true,
        65514 : true,
        65515 : true,
        65516 : true
    };
    this.modifiers = new Guacamole.Keyboard.ModifierState();
    this.pressed = {};
    var n = {};
    var l = {};
    var d = null;
    var o = null;
    function m(B, A) {
        if (!B) {
            return null
        }
        return B[A] || B[0]
    }
    function y(C, B, F) {
        if (!C) {
            return null
        }
        var E;
        var A = C.indexOf("U+");
        if (A >= 0) {
            var D = C.substring(A + 2);
            E = String.fromCharCode(parseInt(D, 16))
        } else {
            if (C.length === 1) {
                E = C
            } else {
                return m(g[C], B)
            }
        }
        if (F === true) {
            E = E.toUpperCase()
        } else {
            if (F === false) {
                E = E.toLowerCase()
            }
        }
        var G = E.charCodeAt(0);
        return r(G)
    }
    function p(A) {
        return A <= 31 || (A >= 127 && A <= 159)
    }
    function r(A) {
        if (p(A)) {
            return 65280 | A
        }
        if (A >= 0 && A <= 255) {
            return A
        }
        if (A >= 256 && A <= 1114111) {
            return 16777216 | A
        }
        return null
    }
    function i(B, A) {
        return m(u[B], A)
    }
    function f(D, C) {
        if (!C) {
            return false
        }
        var A = C.indexOf("U+");
        if (A === -1) {
            return true
        }
        var B = parseInt(C.substring(A + 2), 16);
        if (D !== B) {
            return true
        }
        if ((D >= 65 && D <= 90) || (D >= 48 && D <= 57)) {
            return true
        }
        return false
    }
    this.press = function (B) {
        if (B === null) {
            return
        }
        if (!j.pressed[B]) {
            j.pressed[B] = true;
            if (j.onkeydown) {
                var A = j.onkeydown(B);
                n[B] = A;
                window.clearTimeout(d);
                window.clearInterval(o);
                if (!s[B]) {
                    d = window.setTimeout(function () {
                        o = window.setInterval(function () {
                            j.onkeyup(B);
                            j.onkeydown(B)
                        }, 50)
                    }, 500)
                }
                return A
            }
        }
        return n[B] || false
    };
    this.release = function (A) {
        if (j.pressed[A]) {
            delete j.pressed[A];
            window.clearTimeout(d);
            window.clearInterval(o);
            if (A !== null && j.onkeyup) {
                j.onkeyup(A)
            }
        }
    };
    this.reset = function () {
        for (var A in j.pressed) {
            j.release(parseInt(A))
        }
        z = []
    };
    function a(B) {
        var A = Guacamole.Keyboard.ModifierState.fromKeyboardEvent(B);
        if (j.modifiers.alt && A.alt === false) {
            j.release(65513);
            j.release(65514);
            j.release(65027)
        }
        if (j.modifiers.shift && A.shift === false) {
            j.release(65505);
            j.release(65506)
        }
        if (j.modifiers.ctrl && A.ctrl === false) {
            j.release(65507);
            j.release(65508)
        }
        if (j.modifiers.meta && A.meta === false) {
            j.release(65511);
            j.release(65512)
        }
        if (j.modifiers.hyper && A.hyper === false) {
            j.release(65515);
            j.release(65516)
        }
        j.modifiers = A
    }
    function h() {
        var B = q();
        if (!B) {
            return false
        }
        var A;
        do {
            A = B;
            B = q()
        } while (B !== null);
        return A.defaultPrevented
    }
    function t(A) {
        if (!j.modifiers.ctrl || !j.modifiers.alt) {
            return
        }
        if (A >= 65 && A <= 90) {
            return
        }
        if (A >= 97 && A <= 122) {
            return
        }
        if (A <= 255 || (A & 4278190080) === 16777216) {
            j.release(65507);
            j.release(65508);
            j.release(65513);
            j.release(65514)
        }
    }
    function q() {
        var D = z[0];
        if (!D) {
            return null
        }
        if (D instanceof w) {
            var C = null;
            var E = [];
            if (D.reliable) {
                C = D.keysym;
                E = z.splice(0, 1)
            } else {
                if (z[1]instanceof b) {
                    C = z[1].keysym;
                    E = z.splice(0, 2)
                } else {
                    if (z[1]) {
                        C = D.keysym;
                        E = z.splice(0, 1)
                    }
                }
            }
            if (E.length > 0) {
                if (C) {
                    t(C);
                    var A = !j.press(C);
                    l[D.keyCode] = C;
                    if (j.modifiers.meta && C !== 65511 && C !== 65512) {
                        j.release(C)
                    }
                    for (var B = 0; B < E.length; B++) {
                        E[B].defaultPrevented = A
                    }
                }
                return D
            }
        } else {
            if (D instanceof k) {
                var C = D.keysym;
                if (C) {
                    j.release(C);
                    D.defaultPrevented = true
                } else {
                    j.reset();
                    return D
                }
                return z.shift()
            } else {
                return z.shift()
            }
        }
        return null
    }
    var x = function x(A) {
        if ("location" in A) {
            return A.location
        }
        if ("keyLocation" in A) {
            return A.keyLocation
        }
        return 0
    };
    c.addEventListener("keydown", function (C) {
        if (!j.onkeydown) {
            return
        }
        var B;
        if (window.event) {
            B = window.event.keyCode
        } else {
            if (C.which) {
                B = C.which
            }
        }
        a(C);
        if (B === 229) {
            return
        }
        var A = new w(B, C.keyIdentifier, C.key, x(C));
        z.push(A);
        if (h()) {
            C.preventDefault()
        }
    }, true);
    c.addEventListener("keypress", function (C) {
        if (!j.onkeydown && !j.onkeyup) {
            return
        }
        var B;
        if (window.event) {
            B = window.event.keyCode
        } else {
            if (C.which) {
                B = C.which
            }
        }
        a(C);
        var A = new b(B);
        z.push(A);
        if (h()) {
            C.preventDefault()
        }
    }, true);
    c.addEventListener("keyup", function (C) {
        if (!j.onkeyup) {
            return
        }
        C.preventDefault();
        var B;
        if (window.event) {
            B = window.event.keyCode
        } else {
            if (C.which) {
                B = C.which
            }
        }
        a(C);
        var A = new k(B, C.keyIdentifier, C.key, x(C));
        z.push(A);
        h()
    }, true)
};
Guacamole.Keyboard.ModifierState = function () {
    this.shift = false;
    this.ctrl = false;
    this.alt = false;
    this.meta = false;
    this.hyper = false
};
Guacamole.Keyboard.ModifierState.fromKeyboardEvent = function (b) {
    var a = new Guacamole.Keyboard.ModifierState();
    a.shift = b.shiftKey;
    a.ctrl = b.ctrlKey;
    a.alt = b.altKey;
    a.meta = b.metaKey;
    if (b.getModifierState) {
        a.hyper = b.getModifierState("OS") || b.getModifierState("Super") || b.getModifierState("Hyper") || b.getModifierState("Win")
    }
    return a
};
var Guacamole = Guacamole || {};
Guacamole.Layer = function (a, k) {
    var h = this;
    var d = document.createElement("canvas");
    var b = d.getContext("2d");
    b.save();
    var f = true;
    var j = 0;
    var i = {
        1 : "destination-in",
        2 : "destination-out",
        4 : "source-in",
        6 : "source-atop",
        8 : "source-out",
        9 : "destination-atop",
        10 : "xor",
        11 : "destination-over",
        12 : "copy",
        14 : "source-over",
        15 : "lighter"
    };
    function c(o, n) {
        var p = null;
        if (h.width !== 0 && h.height !== 0) {
            p = document.createElement("canvas");
            p.width = h.width;
            p.height = h.height;
            var m = p.getContext("2d");
            m.drawImage(d, 0, 0, h.width, h.height, 0, 0, h.width, h.height)
        }
        var l = b.globalCompositeOperation;
        d.width = o;
        d.height = n;
        if (p) {
            b.drawImage(p, 0, 0, h.width, h.height, 0, 0, h.width, h.height)
        }
        b.globalCompositeOperation = l;
        h.width = o;
        h.height = n;
        j = 0;
        b.save()
    }
    function g(l, s, m, n) {
        var q = m + l;
        var p = n + s;
        var o;
        if (q > h.width) {
            o = q
        } else {
            o = h.width
        }
        var r;
        if (p > h.height) {
            r = p
        } else {
            r = h.height
        }
        h.resize(o, r)
    }
    this.autosize = false;
    this.width = a;
    this.height = k;
    this.getCanvas = function () {
        return d
    };
    this.resize = function (m, l) {
        if (m !== h.width || l !== h.height) {
            c(m, l)
        }
    };
    this.drawImage = function (l, n, m) {
        if (h.autosize) {
            g(l, n, m.width, m.height)
        }
        b.drawImage(m, l, n)
    };
    this.transfer = function (v, o, m, p, A, w, u, r) {
        var q = v.getCanvas();
        if (o >= q.width || m >= q.height) {
            return
        }
        if (o + p > q.width) {
            p = q.width - o
        }
        if (m + A > q.height) {
            A = q.height - m
        }
        if (p === 0 || A === 0) {
            return
        }
        if (h.autosize) {
            g(w, u, p, A)
        }
        var l = v.getCanvas().getContext("2d").getImageData(o, m, p, A);
        var t = b.getImageData(w, u, p, A);
        for (var s = 0; s < p * A * 4; s += 4) {
            var n = new Guacamole.Layer.Pixel(l.data[s], l.data[s + 1], l.data[s + 2], l.data[s + 3]);
            var z = new Guacamole.Layer.Pixel(t.data[s], t.data[s + 1], t.data[s + 2], t.data[s + 3]);
            r(n, z);
            t.data[s] = z.red;
            t.data[s + 1] = z.green;
            t.data[s + 2] = z.blue;
            t.data[s + 3] = z.alpha
        }
        b.putImageData(t, w, u)
    };
    this.put = function (r, n, m, o, t, s, q) {
        var p = r.getCanvas();
        if (n >= p.width || m >= p.height) {
            return
        }
        if (n + o > p.width) {
            o = p.width - n
        }
        if (m + t > p.height) {
            t = p.height - m
        }
        if (o === 0 || t === 0) {
            return
        }
        if (h.autosize) {
            g(s, q, o, t)
        }
        var l = r.getCanvas().getContext("2d").getImageData(n, m, o, t);
        b.putImageData(l, s, q)
    };
    this.copy = function (p, n, m, o, r, l, s) {
        var q = p.getCanvas();
        if (n >= q.width || m >= q.height) {
            return
        }
        if (n + o > q.width) {
            o = q.width - n
        }
        if (m + r > q.height) {
            r = q.height - m
        }
        if (o === 0 || r === 0) {
            return
        }
        if (h.autosize) {
            g(l, s, o, r)
        }
        b.drawImage(q, n, m, o, r, l, s, o, r)
    };
    this.moveTo = function (l, m) {
        if (f) {
            b.beginPath();
            f = false
        }
        if (h.autosize) {
            g(l, m, 0, 0)
        }
        b.moveTo(l, m)
    };
    this.lineTo = function (l, m) {
        if (f) {
            b.beginPath();
            f = false
        }
        if (h.autosize) {
            g(l, m, 0, 0)
        }
        b.lineTo(l, m)
    };
    this.arc = function (m, q, l, p, n, o) {
        if (f) {
            b.beginPath();
            f = false
        }
        if (h.autosize) {
            g(m, q, 0, 0)
        }
        b.arc(m, q, l, p, n, o)
    };
    this.curveTo = function (n, m, p, o, l, q) {
        if (f) {
            b.beginPath();
            f = false
        }
        if (h.autosize) {
            g(l, q, 0, 0)
        }
        b.bezierCurveTo(n, m, p, o, l, q)
    };
    this.close = function () {
        b.closePath();
        f = true
    };
    this.rect = function (l, o, m, n) {
        if (f) {
            b.beginPath();
            f = false
        }
        if (h.autosize) {
            g(l, o, m, n)
        }
        b.rect(l, o, m, n)
    };
    this.clip = function () {
        b.clip();
        f = true
    };
    this.strokeColor = function (o, s, n, q, p, l, m) {
        b.lineCap = o;
        b.lineJoin = s;
        b.lineWidth = n;
        b.strokeStyle = "rgba(" + q + "," + p + "," + l + "," + m / 255 + ")";
        b.stroke();
        f = true
    };
    this.fillColor = function (o, n, l, m) {
        b.fillStyle = "rgba(" + o + "," + n + "," + l + "," + m / 255 + ")";
        b.fill();
        f = true
    };
    this.strokeLayer = function (n, o, m, l) {
        b.lineCap = n;
        b.lineJoin = o;
        b.lineWidth = m;
        b.strokeStyle = b.createPattern(l.getCanvas(), "repeat");
        b.stroke();
        f = true
    };
    this.fillLayer = function (l) {
        b.fillStyle = b.createPattern(l.getCanvas(), "repeat");
        b.fill();
        f = true
    };
    this.push = function () {
        b.save();
        j++
    };
    this.pop = function () {
        if (j > 0) {
            b.restore();
            j--
        }
    };
    this.reset = function () {
        while (j > 0) {
            b.restore();
            j--
        }
        b.restore();
        b.save();
        b.beginPath();
        f = false
    };
    this.setTransform = function (m, l, q, p, o, n) {
        b.setTransform(m, l, q, p, o, n)
    };
    this.transform = function (m, l, q, p, o, n) {
        b.transform(m, l, q, p, o, n)
    };
    this.setChannelMask = function (l) {
        b.globalCompositeOperation = i[l]
    };
    this.setMiterLimit = function (l) {
        b.miterLimit = l
    };
    d.width = a;
    d.height = k;
    d.style.zIndex = -1
};
Guacamole.Layer.ROUT = 2;
Guacamole.Layer.ATOP = 6;
Guacamole.Layer.XOR = 10;
Guacamole.Layer.ROVER = 11;
Guacamole.Layer.OVER = 14;
Guacamole.Layer.PLUS = 15;
Guacamole.Layer.RIN = 1;
Guacamole.Layer.IN = 4;
Guacamole.Layer.OUT = 8;
Guacamole.Layer.RATOP = 9;
Guacamole.Layer.SRC = 12;
Guacamole.Layer.Pixel = function (h, f, c, d) {
    this.red = h;
    this.green = f;
    this.blue = c;
    this.alpha = d
};
var Guacamole = Guacamole || {};
Guacamole.Mouse = function (g) {
    var f = this;
    this.touchMouseThreshold = 3;
    this.scrollThreshold = 53;
    this.PIXELS_PER_LINE = 18;
    this.PIXELS_PER_PAGE = this.PIXELS_PER_LINE * 16;
    this.currentState = new Guacamole.Mouse.State(0, 0, false, false, false, false, false);
    this.onmousedown = null;
    this.onmouseup = null;
    this.onmousemove = null;
    this.onmouseout = null;
    var h = 0;
    var a = 0;
    function d(j) {
        j.stopPropagation();
        if (j.preventDefault) {
            j.preventDefault()
        }
        j.returnValue = false
    }
    g.addEventListener("contextmenu", function (j) {
        d(j)
    }, false);
    g.addEventListener("mousemove", function (j) {
        d(j);
        if (h) {
            h--;
            return
        }
        f.currentState.fromClientPosition(g, j.clientX, j.clientY);
        if (f.onmousemove) {
            f.onmousemove(f.currentState)
        }
    }, false);
    g.addEventListener("mousedown", function (j) {
        d(j);
        if (h) {
            return
        }
        switch (j.button) {
            case 0:
                f.currentState.left = true;
                break;
            case 1:
                f.currentState.middle = true;
                break;
            case 2:
                f.currentState.right = true;
                break
        }
        if (f.onmousedown) {
            f.onmousedown(f.currentState)
        }
    }, false);
    g.addEventListener("mouseup", function (j) {
        d(j);
        if (h) {
            return
        }
        switch (j.button) {
            case 0:
                f.currentState.left = false;
                break;
            case 1:
                f.currentState.middle = false;
                break;
            case 2:
                f.currentState.right = false;
                break
        }
        if (f.onmouseup) {
            f.onmouseup(f.currentState)
        }
    }, false);
    g.addEventListener("mouseout", function (k) {
        if (!k) {
            k = window.event
        }
        var j = k.relatedTarget || k.toElement;
        while (j) {
            if (j === g) {
                return
            }
            j = j.parentNode
        }
        d(k);
        if (f.currentState.left || f.currentState.middle || f.currentState.right) {
            f.currentState.left = false;
            f.currentState.middle = false;
            f.currentState.right = false;
            if (f.onmouseup) {
                f.onmouseup(f.currentState)
            }
        }
        if (f.onmouseout) {
            f.onmouseout()
        }
    }, false);
    g.addEventListener("selectstart", function (j) {
        d(j)
    }, false);
    function c() {
        h = f.touchMouseThreshold
    }
    g.addEventListener("touchmove", c, false);
    g.addEventListener("touchstart", c, false);
    g.addEventListener("touchend", c, false);
    function i(j) {
        var k = j.deltaY || -j.wheelDeltaY || -j.wheelDelta;
        if (k) {
            if (j.deltaMode === 1) {
                k = j.deltaY * f.PIXELS_PER_LINE
            } else {
                if (j.deltaMode === 2) {
                    k = j.deltaY * f.PIXELS_PER_PAGE
                }
            }
        } else {
            k = j.detail * f.PIXELS_PER_LINE
        }
        a += k;
        if (a <= -f.scrollThreshold) {
            do {
                if (f.onmousedown) {
                    f.currentState.up = true;
                    f.onmousedown(f.currentState)
                }
                if (f.onmouseup) {
                    f.currentState.up = false;
                    f.onmouseup(f.currentState)
                }
                a += f.scrollThreshold
            } while (a <= -f.scrollThreshold);
            a = 0
        }
        if (a >= f.scrollThreshold) {
            do {
                if (f.onmousedown) {
                    f.currentState.down = true;
                    f.onmousedown(f.currentState)
                }
                if (f.onmouseup) {
                    f.currentState.down = false;
                    f.onmouseup(f.currentState)
                }
                a -= f.scrollThreshold
            } while (a >= f.scrollThreshold);
            a = 0
        }
        d(j)
    }
    g.addEventListener("DOMMouseScroll", i, false);
    g.addEventListener("mousewheel", i, false);
    g.addEventListener("wheel", i, false);
    var b = (function () {
        var k = document.createElement("div");
        if (!("cursor" in k.style)) {
            return false
        }
        try {
            k.style.cursor = "url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEX///+nxBvIAAAACklEQVQI12NgAAAAAgAB4iG8MwAAAABJRU5ErkJggg==) 0 0, auto"
        } catch (j) {
            return false
        }
        return /\burl\([^()]*\)\s+0\s+0\b/.test(k.style.cursor || "")
    })();
    this.setCursor = function (k, j, m) {
        if (b) {
            var l = k.toDataURL("image/png");
            g.style.cursor = "url(" + l + ") " + j + " " + m + ", auto";
            return true
        }
        return false
    }
};
Guacamole.Mouse.State = function (b, i, g, c, d, a, h) {
    var f = this;
    this.x = b;
    this.y = i;
    this.left = g;
    this.middle = c;
    this.right = d;
    this.up = a;
    this.down = h;
    this.fromClientPosition = function (k, o, n) {
        f.x = o - k.offsetLeft;
        f.y = n - k.offsetTop;
        var m = k.offsetParent;
        while (m && !(m === document.body)) {
            f.x -= m.offsetLeft - m.scrollLeft;
            f.y -= m.offsetTop - m.scrollTop;
            m = m.offsetParent
        }
        if (m) {
            var l = document.body.scrollLeft || document.documentElement.scrollLeft;
            var j = document.body.scrollTop || document.documentElement.scrollTop;
            f.x -= m.offsetLeft - l;
            f.y -= m.offsetTop - j
        }
    }
};
Guacamole.Mouse.Touchpad = function (d) {
    var c = this;
    this.scrollThreshold = 20 * (window.devicePixelRatio || 1);
    this.clickTimingThreshold = 250;
    this.clickMoveThreshold = 10 * (window.devicePixelRatio || 1);
    this.currentState = new Guacamole.Mouse.State(0, 0, false, false, false, false, false);
    this.onmousedown = null;
    this.onmouseup = null;
    this.onmousemove = null;
    var a = 0;
    var i = 0;
    var h = 0;
    var g = 0;
    var f = 0;
    var b = {
        1 : "left",
        2 : "right",
        3 : "middle"
    };
    var k = false;
    var j = null;
    d.addEventListener("touchend", function (n) {
        n.preventDefault();
        if (k && n.touches.length === 0) {
            var m = new Date().getTime();
            var l = b[a];
            if (c.currentState[l]) {
                c.currentState[l] = false;
                if (c.onmouseup) {
                    c.onmouseup(c.currentState)
                }
                if (j) {
                    window.clearTimeout(j);
                    j = null
                }
            }
            if (m - g <= c.clickTimingThreshold && f < c.clickMoveThreshold) {
                c.currentState[l] = true;
                if (c.onmousedown) {
                    c.onmousedown(c.currentState)
                }
                j = window.setTimeout(function () {
                    c.currentState[l] = false;
                    if (c.onmouseup) {
                        c.onmouseup(c.currentState)
                    }
                    k = false
                }, c.clickTimingThreshold)
            }
            if (!j) {
                k = false
            }
        }
    }, false);
    d.addEventListener("touchstart", function (m) {
        m.preventDefault();
        a = Math.min(m.touches.length, 3);
        if (j) {
            window.clearTimeout(j);
            j = null
        }
        if (!k) {
            k = true;
            var l = m.touches[0];
            i = l.clientX;
            h = l.clientY;
            g = new Date().getTime();
            f = 0
        }
    }, false);
    d.addEventListener("touchmove", function (n) {
        n.preventDefault();
        var r = n.touches[0];
        var q = r.clientX - i;
        var p = r.clientY - h;
        f += Math.abs(q) + Math.abs(p);
        if (a === 1) {
            var m = f / (new Date().getTime() - g);
            var o = 1 + m;
            c.currentState.x += q * o;
            c.currentState.y += p * o;
            if (c.currentState.x < 0) {
                c.currentState.x = 0
            } else {
                if (c.currentState.x >= d.offsetWidth) {
                    c.currentState.x = d.offsetWidth - 1
                }
            }
            if (c.currentState.y < 0) {
                c.currentState.y = 0
            } else {
                if (c.currentState.y >= d.offsetHeight) {
                    c.currentState.y = d.offsetHeight - 1
                }
            }
            if (c.onmousemove) {
                c.onmousemove(c.currentState)
            }
            i = r.clientX;
            h = r.clientY
        } else {
            if (a === 2) {
                if (Math.abs(p) >= c.scrollThreshold) {
                    var l;
                    if (p > 0) {
                        l = "down"
                    } else {
                        l = "up"
                    }
                    c.currentState[l] = true;
                    if (c.onmousedown) {
                        c.onmousedown(c.currentState)
                    }
                    c.currentState[l] = false;
                    if (c.onmouseup) {
                        c.onmouseup(c.currentState)
                    }
                    i = r.clientX;
                    h = r.clientY
                }
            }
        }
    }, false)
};
Guacamole.Mouse.Touchscreen = function (f) {
    var d = this;
    var l = false;
    var k = null;
    var j = null;
    var m = null;
    var i = null;
    this.scrollThreshold = 20 * (window.devicePixelRatio || 1);
    this.clickTimingThreshold = 250;
    this.clickMoveThreshold = 16 * (window.devicePixelRatio || 1);
    this.longPressThreshold = 500;
    this.currentState = new Guacamole.Mouse.State(0, 0, false, false, false, false, false);
    this.onmousedown = null;
    this.onmouseup = null;
    this.onmousemove = null;
    function n(p) {
        if (!d.currentState[p]) {
            d.currentState[p] = true;
            if (d.onmousedown) {
                d.onmousedown(d.currentState)
            }
        }
    }
    function h(p) {
        if (d.currentState[p]) {
            d.currentState[p] = false;
            if (d.onmouseup) {
                d.onmouseup(d.currentState)
            }
        }
    }
    function c(p) {
        n(p);
        h(p)
    }
    function b(p, q) {
        d.currentState.fromClientPosition(f, p, q);
        if (d.onmousemove) {
            d.onmousemove(d.currentState)
        }
    }
    function a(p) {
        var s = p.touches[0] || p.changedTouches[0];
        var r = s.clientX - k;
        var q = s.clientY - j;
        return Math.sqrt(r * r + q * q) >= d.clickMoveThreshold
    }
    function g(p) {
        var q = p.touches[0];
        l = true;
        k = q.clientX;
        j = q.clientY
    }
    function o() {
        window.clearTimeout(m);
        window.clearTimeout(i);
        l = false
    }
    f.addEventListener("touchend", function (p) {
        if (!l) {
            return
        }
        if (p.touches.length !== 0 || p.changedTouches.length !== 1) {
            o();
            return
        }
        window.clearTimeout(i);
        h("left");
        if (!a(p)) {
            p.preventDefault();
            if (!d.currentState.left) {
                var q = p.changedTouches[0];
                b(q.clientX, q.clientY);
                n("left");
                m = window.setTimeout(function () {
                    h("left");
                    o()
                }, d.clickTimingThreshold)
            }
        }
    }, false);
    f.addEventListener("touchstart", function (p) {
        if (p.touches.length !== 1) {
            o();
            return
        }
        p.preventDefault();
        g(p);
        window.clearTimeout(m);
        i = window.setTimeout(function () {
            var q = p.touches[0];
            b(q.clientX, q.clientY);
            c("right");
            o()
        }, d.longPressThreshold)
    }, false);
    f.addEventListener("touchmove", function (p) {
        if (!l) {
            return
        }
        if (a(p)) {
            window.clearTimeout(i)
        }
        if (p.touches.length !== 1) {
            o();
            return
        }
        if (d.currentState.left) {
            p.preventDefault();
            var q = p.touches[0];
            b(q.clientX, q.clientY)
        }
    }, false)
};
var Guacamole = Guacamole || {};
Guacamole.Object = function guacamoleObject(f, g) {
    var h = this;
    var i = {};
    var a = function a(k) {
        var l = i[k];
        if (!l) {
            return null
        }
        var m = l.shift();
        if (l.length === 0) {
            delete i[k]
        }
        return m
    };
    var d = function d(k, m) {
        var l = i[k];
        if (!l) {
            l = [];
            i[k] = l
        }
        l.push(m)
    };
    this.index = g;
    this.onbody = function j(m, k, l) {
        var n = a(l);
        if (n) {
            n(m, k)
        }
    };
    this.onundefine = null;
    this.requestInputStream = function c(l, k) {
        if (k) {
            d(l, k)
        }
        f.requestObjectInputStream(h.index, l)
    };
    this.createOutputStream = function b(k, l) {
        return f.createObjectOutputStream(h.index, k, l)
    }
};
Guacamole.Object.ROOT_STREAM = "/";
Guacamole.Object.STREAM_INDEX_MIMETYPE = "application/vnd.glyptodon.guacamole.stream-index+json";
var Guacamole = Guacamole || {};
Guacamole.OnScreenKeyboard = function (r) {
    var f = this;
    var i = {};
    var b = {};
    var d = [];
    var j = function j(v, w) {
        if (v.classList) {
            v.classList.add(w)
        } else {
            v.className += " " + w
        }
    };
    var k = function k(v, x) {
        if (v.classList) {
            v.classList.remove(x)
        } else {
            v.className = v.className.replace(/([^ ]+)[ ]*/g, function w(z, y) {
                if (y === x) {
                    return ""
                }
                return z
            })
        }
    };
    var u = 0;
    var g = function g() {
        u = f.touchMouseThreshold
    };
    var s = function s(w, x, v, y) {
        this.width = x;
        this.height = v;
        this.scale = function (z) {
            w.style.width = (x * z) + "px";
            w.style.height = (v * z) + "px";
            if (y) {
                w.style.lineHeight = (v * z) + "px";
                w.style.fontSize = z + "px"
            }
        }
    };
    var m = function m(x) {
        for (var w = 0; w < x.length; w++) {
            var v = x[w];
            if (!(v in i)) {
                return false
            }
        }
        return true
    };
    var h = function h(x) {
        var y = f.keys[x];
        if (!y) {
            return null
        }
        for (var v = y.length - 1; v >= 0; v--) {
            var w = y[v];
            if (m(w.requires)) {
                return w
            }
        }
        return null
    };
    var n = function n(y, z) {
        if (!b[y]) {
            j(z, "guac-keyboard-pressed");
            var x = h(y);
            if (x.modifier) {
                var w = "guac-keyboard-modifier-" + o(x.modifier);
                var v = i[x.modifier];
                if (!v) {
                    j(c, w);
                    i[x.modifier] = x.keysym;
                    if (f.onkeydown) {
                        f.onkeydown(x.keysym)
                    }
                } else {
                    k(c, w);
                    delete i[x.modifier];
                    if (f.onkeyup) {
                        f.onkeyup(v)
                    }
                }
            } else {
                if (f.onkeydown) {
                    f.onkeydown(x.keysym)
                }
            }
            b[y] = true
        }
    };
    var p = function p(w, x) {
        if (b[w]) {
            k(x, "guac-keyboard-pressed");
            var v = h(w);
            if (!v.modifier && f.onkeyup) {
                f.onkeyup(v.keysym)
            }
            b[w] = false
        }
    };
    var c = document.createElement("div");
    c.className = "guac-keyboard";
    c.onselectstart = c.onmousemove = c.onmouseup = c.onmousedown = function l(v) {
        if (u) {
            u--
        }
        v.stopPropagation();
        return false
    };
    this.touchMouseThreshold = 3;
    this.onkeydown = null;
    this.onkeyup = null;
    this.layout = new Guacamole.OnScreenKeyboard.Layout(r);
    this.getElement = function () {
        return c
    };
    this.resize = function (x) {
        var y = Math.floor(x * 10 / f.layout.width) / 10;
        for (var w = 0; w < d.length; w++) {
            var v = d[w];
            v.scale(y)
        }
    };
    var t = function t(w, v) {
        if (v instanceof Array) {
            var y = [];
            for (var x = 0; x < v.length; x++) {
                y.push(new Guacamole.OnScreenKeyboard.Key(v[x], w))
            }
            return y
        }
        if (typeof v === "number") {
            return [new Guacamole.OnScreenKeyboard.Key({
                name : w,
                keysym : v
            })]
        }
        if (typeof v === "string") {
            return [new Guacamole.OnScreenKeyboard.Key({
                name : w,
                title : v
            })]
        }
        return [new Guacamole.OnScreenKeyboard.Key(v, w)]
    };
    var a = function a(x) {
        var w = {};
        for (var v in r.keys) {
            w[v] = t(v, x[v])
        }
        return w
    };
    this.keys = a(r.keys);
    var o = function o(v) {
        var w = v.replace(/([a-z])([A-Z])/g, "$1-$2").replace(/[^A-Za-z0-9]+/g, "-").toLowerCase();
        return w
    };
    var q = function q(B, y, w) {
        var C;
        var v = document.createElement("div");
        if (w) {
            j(v, "guac-keyboard-" + o(w))
        }
        if (y instanceof Array) {
            j(v, "guac-keyboard-group");
            for (C = 0; C < y.length; C++) {
                q(v, y[C])
            }
        } else {
            if (y instanceof Object) {
                j(v, "guac-keyboard-group");
                var G = Object.keys(y).sort();
                for (C = 0; C < G.length; C++) {
                    var w = G[C];
                    q(v, y[w], w)
                }
            } else {
                if (typeof y === "number") {
                    j(v, "guac-keyboard-gap");
                    d.push(new s(v, y, y))
                } else {
                    if (typeof y === "string") {
                        var I = y;
                        if (I.length === 1) {
                            I = "0x" + I.charCodeAt(0).toString(16)
                        }
                        j(v, "guac-keyboard-key-container");
                        var K = document.createElement("div");
                        K.className = "guac-keyboard-key guac-keyboard-key-" + o(I);
                        var J = f.keys[y];
                        if (J) {
                            for (C = 0; C < J.length; C++) {
                                var H = J[C];
                                var F = document.createElement("div");
                                F.className = "guac-keyboard-cap";
                                F.textContent = H.title;
                                for (var z = 0; z < H.requires.length; z++) {
                                    var L = H.requires[z];
                                    j(F, "guac-keyboard-requires-" + o(L));
                                    j(K, "guac-keyboard-uses-" + o(L))
                                }
                                K.appendChild(F)
                            }
                        }
                        v.appendChild(K);
                        d.push(new s(v, f.layout.keyWidths[y] || 1, 1, true));
                        var E = function E(M) {
                            M.preventDefault();
                            u = f.touchMouseThreshold;
                            n(y, K)
                        };
                        var x = function x(M) {
                            M.preventDefault();
                            u = f.touchMouseThreshold;
                            p(y, K)
                        };
                        var A = function A(M) {
                            M.preventDefault();
                            if (u === 0) {
                                n(y, K)
                            }
                        };
                        var D = function D(M) {
                            M.preventDefault();
                            if (u === 0) {
                                p(y, K)
                            }
                        };
                        K.addEventListener("touchstart", E, true);
                        K.addEventListener("touchend", x, true);
                        K.addEventListener("mousedown", A, true);
                        K.addEventListener("mouseup", D, true);
                        K.addEventListener("mouseout", D, true)
                    }
                }
            }
        }
        B.appendChild(v)
    };
    q(c, r.layout)
};
Guacamole.OnScreenKeyboard.Layout = function (a) {
    this.language = a.language;
    this.type = a.type;
    this.keys = a.keys;
    this.layout = a.layout;
    this.width = a.width;
    this.keyWidths = a.keyWidths || {}

};
Guacamole.OnScreenKeyboard.Key = function (c, a) {
    this.name = a || c.name;
    this.title = c.title || this.name;
    this.keysym = c.keysym || (function b(f) {
            if (!f || f.length !== 1) {
                return null
            }
            var d = f.charCodeAt(0);
            if (d >= 0 && d <= 255) {
                return d
            }
            if (d >= 256 && d <= 1114111) {
                return 16777216 | d
            }
            return null
        })(this.title);
    this.modifier = c.modifier;
    this.requires = c.requires || []
};
var Guacamole = Guacamole || {};
Guacamole.OutputStream = function (a, b) {
    var c = this;
    this.index = b;
    this.onack = null;
    this.sendBlob = function (d) {
        a.sendBlob(c.index, d)
    };
    this.sendEnd = function () {
        a.endStream(c.index)
    }
};
var Guacamole = Guacamole || {};
Guacamole.Parser = function () {
    var f = this;
    var b = "";
    var d = [];
    var c = -1;
    var a = 0;
    this.receive = function (l) {
        if (a > 4096 && c >= a) {
            b = b.substring(a);
            c -= a;
            a = 0
        }
        b += l;
        while (c < b.length) {
            if (c >= a) {
                var i = b.substring(a, c);
                var h = b.substring(c, c + 1);
                d.push(i);
                if (h == ";") {
                    var k = d.shift();
                    if (f.oninstruction != null) {
                        f.oninstruction(k, d)
                    }
                    d.length = 0
                } else {
                    if (h != ",") {
                        throw new Error("Illegal terminator.")
                    }
                }
                a = c + 1
            }
            var g = b.indexOf(".", a);
            if (g != -1) {
                var j = parseInt(b.substring(c + 1, g));
                if (j == NaN) {
                    throw new Error("Non-numeric character in element length.")
                }
                a = g + 1;
                c = a + j
            } else {
                a = b.length;
                break
            }
        }
    };
    this.oninstruction = null
};
var Guacamole = Guacamole || {};
Guacamole.Status = function (b, a) {
    var c = this;
    this.code = b;
    this.message = a;
    this.isError = function () {
        return c.code < 0 || c.code > 255
    }
};
Guacamole.Status.Code = {
    SUCCESS : 0,
    UNSUPPORTED : 256,
    SERVER_ERROR : 512,
    SERVER_BUSY : 513,
    UPSTREAM_TIMEOUT : 514,
    UPSTREAM_ERROR : 515,
    RESOURCE_NOT_FOUND : 516,
    RESOURCE_CONFLICT : 517,
    CLIENT_BAD_REQUEST : 768,
    CLIENT_UNAUTHORIZED : 769,
    CLIENT_FORBIDDEN : 771,
    CLIENT_TIMEOUT : 776,
    CLIENT_OVERRUN : 781,
    CLIENT_BAD_TYPE : 783,
    CLIENT_TOO_MANY : 797
};
var Guacamole = Guacamole || {};
Guacamole.StringReader = function (f) {
    var d = this;
    var b = new Guacamole.ArrayBufferReader(f);
    var g = 0;
    var c = 0;
    function a(j) {
        var m = "";
        var h = new Uint8Array(j);
        for (var k = 0; k < h.length; k++) {
            var l = h[k];
            if (g === 0) {
                if ((l | 127) === 127) {
                    m += String.fromCharCode(l)
                } else {
                    if ((l | 31) === 223) {
                        c = l & 31;
                        g = 1
                    } else {
                        if ((l | 15) === 239) {
                            c = l & 15;
                            g = 2
                        } else {
                            if ((l | 7) === 247) {
                                c = l & 7;
                                g = 3
                            } else {
                                m += "\uFFFD"
                            }
                        }
                    }
                }
            } else {
                if ((l | 63) === 191) {
                    c = (c << 6) | (l & 63);
                    g--;
                    if (g === 0) {
                        m += String.fromCharCode(c)
                    }
                } else {
                    g = 0;
                    m += "\uFFFD"
                }
            }
        }
        return m
    }
    b.ondata = function (h) {
        var i = a(h);
        if (d.ontext) {
            d.ontext(i)
        }
    };
    b.onend = function () {
        if (d.onend) {
            d.onend()
        }
    };
    this.ontext = null;
    this.onend = null
};
var Guacamole = Guacamole || {};
Guacamole.StringWriter = function (i) {
    var f = this;
    var c = new Guacamole.ArrayBufferWriter(i);
    var a = new Uint8Array(8192);
    var h = 0;
    c.onack = function (j) {
        if (f.onack) {
            f.onack(j)
        }
    };
    function g(j) {
        if (h + j >= a.length) {
            var k = new Uint8Array((h + j) * 2);
            k.set(a);
            a = k
        }
        h += j
    }
    function d(m) {
        var k;
        var j;
        if (m <= 127) {
            k = 0;
            j = 1
        } else {
            if (m <= 2047) {
                k = 192;
                j = 2
            } else {
                if (m <= 65535) {
                    k = 224;
                    j = 3
                } else {
                    if (m <= 2097151) {
                        k = 240;
                        j = 4
                    } else {
                        d(65533);
                        return
                    }
                }
            }
        }
        g(j);
        var n = h - 1;
        for (var l = 1; l < j; l++) {
            a[n--] = 128 | (m & 63);
            m >>= 6
        }
        a[n] = k | m
    }
    function b(m) {
        for (var j = 0; j < m.length; j++) {
            var l = m.charCodeAt(j);
            d(l)
        }
        if (h > 0) {
            var k = a.subarray(0, h);
            h = 0;
            return k
        }
    }
    this.sendText = function (j) {
        c.sendData(b(j))
    };
    this.sendEnd = function () {
        c.sendEnd()
    };
    this.onack = null
};
var Guacamole = Guacamole || {};
Guacamole.Tunnel = function () {
    this.connect = function (a) {};
    this.disconnect = function () {};
    this.sendMessage = function (a) {};
    this.state = Guacamole.Tunnel.State.CONNECTING;
    this.receiveTimeout = 15000;
    this.onerror = null;
    this.onstatechange = null;
    this.oninstruction = null
};
Guacamole.Tunnel.State = {
    CONNECTING : 0,
    OPEN : 1,
    CLOSED : 2
};
Guacamole.HTTPTunnel = function (h, m, port,host) {
    var s = this;
    var k;
    var j = h + "?connect&port="+port+"&hostname="+host;
    var v = h + "?read:";
    var l = h + "?write:";
    var i = 1;
    var n = 0;
    var t = i;
    var q = false;
    var g = "";
    var c = !!m;
    var d = null;
    function b() {
        window.clearTimeout(d);
        d = window.setTimeout(function () {
            a(new Guacamole.Status(Guacamole.Status.Code.UPSTREAM_TIMEOUT, "Server timeout."))
        }, s.receiveTimeout)
    }
    function a(w) {
        if (s.state === Guacamole.Tunnel.State.CLOSED) {
            return
        }
        if (w.code !== Guacamole.Status.Code.SUCCESS && s.onerror) {
            if (s.state === Guacamole.Tunnel.State.CONNECTING || w.code !== Guacamole.Status.Code.RESOURCE_NOT_FOUND) {
                s.onerror(w)
            }
        }
        s.state = Guacamole.Tunnel.State.CLOSED;
        q = false;
        if (s.onstatechange) {
            s.onstatechange(s.state)
        }
    }
    this.sendMessage = function () {
        if (s.state !== Guacamole.Tunnel.State.OPEN) {
            return
        }
        if (arguments.length === 0) {
            return
        }
        function x(A) {
            var z = new String(A);
            return z.length + "." + z
        }
        var y = x(arguments[0]);
        for (var w = 1; w < arguments.length; w++) {
            y += "," + x(arguments[w])
        }
        y += ";";
        g += y;
        if (!q) {
            p()
        }
    };
    function p() {
        if (s.state !== Guacamole.Tunnel.State.OPEN) {
            return
        }
        if (g.length > 0) {
            q = true;
            var w = new XMLHttpRequest();
            w.open("POST", l + k);
            w.withCredentials = c;
            w.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=UTF-8");
            w.onreadystatechange = function () {
                if (w.readyState === 4) {
                    if (w.status !== 200) {
                        o(w)
                    } else {
                        p()
                    }
                }
            };
            w.send(g);
            g = ""
        } else {
            q = false
        }
    }
    function o(y) {
        var x = parseInt(y.getResponseHeader("Guacamole-Status-Code"));
        var w = y.getResponseHeader("Guacamole-Error-Message");
        a(new Guacamole.Status(x, w))
    }
    function u(z) {
        var w = null;
        var B = null;
        var y = 0;
        var x = -1;
        var C = 0;
        var A = new Array();
        function D() {
            if (s.state !== Guacamole.Tunnel.State.OPEN) {
                if (w !== null) {
                    clearInterval(w)
                }
                return
            }
            if (z.readyState < 2) {
                return
            }
            var E;
            try {
                E = z.status
            } catch (L) {
                E = 200
            }
            if (!B && E === 200) {
                B = r()
            }
            if (z.readyState === 3 || z.readyState === 4) {
                b();
                if (t === i) {
                    if (z.readyState === 3 && !w) {
                        w = setInterval(D, 30)
                    } else {
                        if (z.readyState === 4 && !w) {
                            clearInterval(w)
                        }
                    }
                }
                if (z.status === 0) {
                    s.disconnect();
                    return
                } else {
                    if (z.status !== 200) {
                        o(z);
                        return
                    }
                }
                var K;
                try {
                    K = z.responseText
                } catch (L) {
                    return
                }
                while (x < K.length) {
                    if (x >= C) {
                        var G = K.substring(C, x);
                        var F = K.substring(x, x + 1);
                        A.push(G);
                        if (F === ";") {
                            var J = A.shift();
                            if (s.oninstruction) {
                                s.oninstruction(J, A)
                            }
                            A.length = 0
                        }
                        C = x + 1
                    }
                    var H = K.indexOf(".", C);
                    if (H !== -1) {
                        var I = parseInt(K.substring(x + 1, H));
                        if (I === 0) {
                            if (!w) {
                                clearInterval(w)
                            }
                            z.onreadystatechange = null;
                            z.abort();
                            if (B) {
                                u(B)
                            }
                            break
                        }
                        C = H + 1;
                        x = C + I
                    } else {
                        C = K.length;
                        break
                    }
                }
            }
        }
        if (t === i) {
            z.onreadystatechange = function () {
                if (z.readyState === 3) {
                    y++;
                    if (y >= 2) {
                        t = n;
                        z.onreadystatechange = D
                    }
                }
                D()
            }
        } else {
            z.onreadystatechange = D
        }
        D()
    }
    var f = 0;
    function r() {
        var w = new XMLHttpRequest();
        w.open("GET", v + k + ":" + (f++));
        w.withCredentials = c;
        w.send(null);
        return w
    }
    this.connect = function (w) {
        b();
        var x = new XMLHttpRequest();
        x.onreadystatechange = function () {
            if (x.readyState !== 4) {
                return
            }
            if (x.status !== 200) {
                o(x);
                return
            }
            b();
            k = x.responseText;
            s.state = Guacamole.Tunnel.State.OPEN;
            if (s.onstatechange) {
                s.onstatechange(s.state)
            }
            u(r())
        };
        x.open("GET", j, true);
        x.withCredentials = c;
        x.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=UTF-8");
        x.send(w)
    };
    this.disconnect = function () {
        a(new Guacamole.Status(Guacamole.Status.Code.SUCCESS, "Manually closed."))
    }
};
Guacamole.HTTPTunnel.prototype = new Guacamole.Tunnel();
Guacamole.WebSocketTunnel = function (c) {
    var f = this;
    var g = null;
    var a = null;
    var b = {
        "http:" : "ws:",
        "https:" : "wss:"
    };
    if (c.substring(0, 3) !== "ws:" && c.substring(0, 4) !== "wss:") {
        var i = b[window.location.protocol];
        if (c.substring(0, 1) === "/") {
            c = i + "//" + window.location.host + c
        } else {
            var d = window.location.pathname.lastIndexOf("/");
            var k = window.location.pathname.substring(0, d + 1);
            c = i + "//" + window.location.host + k + c
        }
    }
    function j() {
        window.clearTimeout(a);
        a = window.setTimeout(function () {
            h(new Guacamole.Status(Guacamole.Status.Code.UPSTREAM_TIMEOUT, "Server timeout."))
        }, f.receiveTimeout)
    }
    function h(l) {
        if (f.state === Guacamole.Tunnel.State.CLOSED) {
            return
        }
        if (l.code !== Guacamole.Status.Code.SUCCESS && f.onerror) {
            f.onerror(l)
        }
        f.state = Guacamole.Tunnel.State.CLOSED;
        if (f.onstatechange) {
            f.onstatechange(f.state)
        }
        g.close()
    }
    this.sendMessage = function (o) {
        if (f.state !== Guacamole.Tunnel.State.OPEN) {
            return
        }
        if (arguments.length === 0) {
            return
        }
        function m(q) {
            var p = new String(q);
            return p.length + "." + p
        }
        var n = m(arguments[0]);
        for (var l = 1; l < arguments.length; l++) {
            n += "," + m(arguments[l])
        }
        n += ";";
        g.send(n)
    };
    this.connect = function (l) {
        j();
        g = new WebSocket(c + "?" + l, "guacamole");
        g.onopen = function (m) {
            j();
            f.state = Guacamole.Tunnel.State.OPEN;
            if (f.onstatechange) {
                f.onstatechange(f.state)
            }
        };
        g.onclose = function (m) {
            h(new Guacamole.Status(parseInt(m.reason), m.reason))
        };
        g.onerror = function (m) {
            h(new Guacamole.Status(Guacamole.Status.Code.SERVER_ERROR, m.data))
        };
        g.onmessage = function (o) {
            j();
            var v = o.data;
            var t = 0;
            var s;
            var n = [];
            do {
                var u = v.indexOf(".", t);
                if (u !== -1) {
                    var p = parseInt(v.substring(s + 1, u));
                    t = u + 1;
                    s = t + p
                } else {
                    h(new Guacamole.Status(Guacamole.Status.Code.SERVER_ERROR, "Incomplete instruction."))
                }
                var r = v.substring(t, s);
                var m = v.substring(s, s + 1);
                n.push(r);
                if (m === ";") {
                    var q = n.shift();
                    if (f.oninstruction) {
                        f.oninstruction(q, n)
                    }
                    n.length = 0
                }
                t = s + 1
            } while (t < v.length)
        }
    };
    this.disconnect = function () {
        h(new Guacamole.Status(Guacamole.Status.Code.SUCCESS, "Manually closed."))
    }
};
Guacamole.WebSocketTunnel.prototype = new Guacamole.Tunnel();
Guacamole.ChainedTunnel = function (f) {
    var c = this;
    var a;
    var h = [];
    var g = null;
    for (var d = 0; d < arguments.length; d++) {
        h.push(arguments[d])
    }
    function b(k) {
        c.disconnect = k.disconnect;
        c.sendMessage = k.sendMessage;
        function j() {
            var l = h.shift();
            if (l) {
                k.onerror = null;
                k.oninstruction = null;
                k.onstatechange = null;
                b(l)
            }
            return l
        }
        function i() {
            k.onstatechange = c.onstatechange;
            k.oninstruction = c.oninstruction;
            k.onerror = c.onerror;
            g = k
        }
        k.onstatechange = function (l) {
            switch (l) {
                case Guacamole.Tunnel.State.OPEN:
                    i();
                    if (c.onstatechange) {
                        c.onstatechange(l)
                    }
                    break;
                case Guacamole.Tunnel.State.CLOSED:
                    if (!j() && c.onstatechange) {
                        c.onstatechange(l)
                    }
                    break
            }
        };
        k.oninstruction = function (m, l) {
            i();
            if (c.oninstruction) {
                c.oninstruction(m, l)
            }
        };
        k.onerror = function (l) {
            if (!j() && c.onerror) {
                c.onerror(l)
            }
        };
        k.connect(a)
    }
    this.connect = function (j) {
        a = j;
        var i = g ? g : h.shift();
        if (i) {
            b(i)
        } else {
            if (c.onerror) {
                c.onerror(Guacamole.Status.Code.SERVER_ERROR, "No tunnels to try.")
            }
        }
    }
};
Guacamole.ChainedTunnel.prototype = new Guacamole.Tunnel();
var Guacamole = Guacamole || {};
Guacamole.API_VERSION = "0.9.5";